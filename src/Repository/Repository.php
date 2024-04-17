<?php

declare( strict_types=1 );

namespace App\Repository;

use DateInterval;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

/**
 * A Repository is responsible for retrieving data from wherever it lives (databases, APIs, filesystems, etc.).
 *
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 *
 * @codeCoverageIgnore
 */
abstract class Repository extends EntityRepository {
	/**
	 * The default query timeout in seconds.
	 * @var int
	 * @fixme This should be configurable by environment.
	 */
	public const APP_QUERY_TIMEOUT = 300;

	/** @var LoggerInterface The log. */
	protected LoggerInterface $log;

	/** @var Connection The connection to the eventmetrics database. */
	protected Connection $eventMetricsConnection;

	/** @var Connection The CentralAuth database connection. */
	protected Connection $centralAuthConnection;

	/** @var Connection The meta database connection. */
	protected Connection $metaConnection;

	/**
	 * Create a new Repository with a null logger. Each Repository should define getEntityClass(),
	 * which gets passed into the parent EntityRepository construct.
	 * @param EntityManagerInterface $em The Doctrine entity manager.
	 */
	public function __construct(
		protected EntityManagerInterface $em,
		protected CacheItemPoolInterface $cache,
		protected ManagerRegistry $managerRegistry,
		protected ReplicasClient $replicasClient
	) {
		$metadata = $em->getClassMetadata( $this->getEntityClass() );
		parent::__construct( $em, $metadata );
		$this->log = new NullLogger();
	}

	/**
	 * The name of the Entity class associated with the Repository (such as Program, Organizer, etc.).
	 * This must be defined in every Repository class.
	 * @return string
	 */
	abstract public function getEntityClass(): string;

	/**
	 * Set the logger.
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->log = $logger;
	}

	/*******************
	 * CACHING HELPERS *
	 *******************/

	/**
	 * Get a unique cache key for the given list of arguments. Assuming each argument of
	 * your function should be accounted for, you can pass in them all with func_get_args:
	 *   $this->getCacheKey(func_get_args(), 'unique key for function');
	 * Arguments that are a model should implement their own getCacheKey() that returns
	 * a unique identifier for an instance of that model. See Event::getCacheKey() for example.
	 * @param array|mixed $args Array of arguments or a single argument.
	 * @param string|null $key Unique key for this function. If omitted the function name itself
	 *   is used, which is determined using `debug_backtrace`.
	 * @return string
	 */
	public function getCacheKey( mixed $args, ?string $key = null ): string {
		if ( $key === null ) {
			$key = debug_backtrace()[1]['function'];
		}

		if ( !is_array( $args ) ) {
			$args = [ $args ];
		}

		// Start with base key.
		$cacheKey = $key;

		// Loop through and determine what values to use based on type of object.
		foreach ( $args as $arg ) {
			// Zero is an acceptable value.
			if ( $arg === '' || $arg === null ) {
				continue;
			}

			$cacheKey .= $this->getCacheKeyFromArg( $arg );
		}

		return $cacheKey;
	}

	/**
	 * Get a cache-friendly string given an argument.
	 * @param mixed $arg
	 * @return string
	 */
	private function getCacheKeyFromArg( mixed $arg ): string {
		if ( is_object( $arg ) && method_exists( $arg, 'getCacheKey' ) ) {
			return '.' . $arg->getCacheKey();
		} elseif ( is_array( $arg ) ) {
			// Assumed to be an array of objects that can be parsed into a string.
			return '.' . md5( implode( '', $arg ) );
		} elseif ( $arg instanceof DateTime ) {
			return $arg->format( 'YmdHis' );
		} else {
			// Assumed to be a string, number or boolean.
			return '.' . md5( (string)$arg );
		}
	}

	/**
	 * Set the cache with given options.
	 * @param string $cacheKey
	 * @param mixed $value
	 * @param string $duration Valid DateInterval string.
	 * @return mixed The given $value.
	 */
	public function setCache( string $cacheKey, mixed $value, string $duration = 'PT10M' ): mixed {
		$cacheItem = $this->cache
			->getItem( $cacheKey )
			->set( $value )
			->expiresAfter( new DateInterval( $duration ) );
		$this->cache->save( $cacheItem );
		return $value;
	}

	/***************
	 * CONNECTIONS *
	 ***************/

	/**
	 * Get the database connection for the 'eventmetrics' database.
	 * @return Connection
	 */
	protected function getEventMetricsConnection(): Connection {
		if ( !isset( $this->eventMetricsConnection ) ) {
			$this->eventMetricsConnection = $this->managerRegistry
				->getManager( 'eventmetrics' )
				->getConnection();
		}
		return $this->eventMetricsConnection;
	}

	/**
	 * Get the database connection for the replicas.
	 * @return Connection
	 */
	protected function getCentralAuthConnection(): Connection {
		if ( !isset( $this->centralAuthConnection ) ) {
			$this->centralAuthConnection = $this->managerRegistry
				->getManager( 'centralauth' )
				->getConnection();
		}
		return $this->centralAuthConnection;
	}

	/**
	 * Get the database connection for the replicas.
	 * @return Connection
	 */
	protected function getMetaConnection(): Connection {
		if ( !isset( $this->metaConnection ) ) {
			$this->metaConnection = $this->managerRegistry
				->getManager( 'meta' )
				->getConnection();
		}
		return $this->metaConnection;
	}

	/*************
	 * USERNAMES *
	 *************/

	/**
	 * Get the global user IDs for multiple users, based on the central auth database.
	 * @param string[] $usernames Usernames to query for.
	 * @return string[] with keys 'user_name' and 'user_id'.
	 * FIXME: add caching.
	 */
	public function getUserIdsFromNames( array $usernames ): array {
		$rqb = $this->getCentralAuthConnection()->createQueryBuilder();
		$rqb->select( [ 'gu_name AS user_name', 'gu_id AS user_id' ] )
			->from( 'globaluser' )
			->andWhere( 'gu_name IN (:usernames)' )
			->setParameter( 'usernames', $usernames, Connection::PARAM_STR_ARRAY );
		return $this->executeQueryBuilder( $rqb )->fetchAllAssociative();
	}

	/**
	 * Get the global user ID for the given username.
	 * @param string $username
	 * @return int|null
	 */
	public function getUserIdFromName( string $username ): ?int {
		$ret = $this->getUserIdsFromNames( [ $username ] );
		return isset( $ret[0]['user_id'] ) ? (int)$ret[0]['user_id'] : null;
	}

	/**
	 * Get the usernames given multiple global user IDs, based on the central auth database.
	 * @param int[] $userIds User IDs to query for.
	 * @return string[] with keys 'user_name' and 'user_id'.
	 * FIXME: add caching.
	 */
	public function getUsernamesFromIds( array $userIds ): array {
		$rqb = $this->getCentralAuthConnection()->createQueryBuilder();
		$rqb->select( [ 'gu_name AS user_name', 'gu_id AS user_id' ] )
			->from( 'globaluser' )
			->andWhere( 'gu_id IN (:userIds)' )
			->setParameter( 'userIds', $userIds, Connection::PARAM_INT_ARRAY );
		// The -1 timeout means do not set a max query time. Here it's really fast,
		// and setting the query timeout actually slows it down.
		return $this->executeQueryBuilder( $rqb, -1 )->fetchAllAssociative();
	}

	/**
	 * Get the username given the global user ID.
	 * @param int $userId
	 * @return string|null
	 */
	public function getUsernameFromId( int $userId ): ?string {
		$ret = $this->getUsernamesFromIds( [ $userId ] );
		return $ret[0]['user_name'] ?? null;
	}

	/**
	 * Get actor IDs for the given usernames.
	 * @param string $dbName
	 * @param string[] $usernames
	 * @return int[]
	 */
	public function getActorIdsFromUsernames( string $dbName, array $usernames ): array {
		$conn = $this->replicasClient->getConnection( $dbName );
		$rqb = $conn->createQueryBuilder();

		$rqb->select( 'actor_id' )
			->from( "$dbName.actor" )
			->where( 'actor_name IN (:usernames)' )
			->setParameter( 'usernames', $usernames, Connection::PARAM_STR_ARRAY );
		return $this->executeQueryBuilder( $rqb )->fetchFirstColumn();
	}

	/*****************
	 * QUERY HELPERS *
	 *****************/

	/**
	 * Get the table name for use when querying the replicas.
	 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
	 * @param string $name Name of table.
	 * @param string|null $suffix Suffix to use instead of _userindex.
	 * @return string
	 */
	protected function getTableName( string $name, ?string $suffix = null ): string {
		if ( $suffix === '' ) {
			return $name;
		} elseif ( $suffix !== null ) {
			return $name . '_' . $suffix;
		}

		// For 'revision' and 'logging' tables (actually views) on the WMF replicas,
		// use the indexed versions (that have some rows hidden, e.g. for revdeleted users).
		$isLoggingOrRevision = in_array( $name, [ 'revision', 'logging', 'archive' ] );
		if ( $isLoggingOrRevision ) {
			$name .= '_userindex';
		}

		return $name;
	}

	/**
	 * Execute a query using the projects connection, handling certain Exceptions.
	 * @param string $dbName
	 * @param string $sql
	 * @param array $params Parameters to bound to the prepared query.
	 * @param int[] $types Optional types of parameters from $params.
	 * @param int|null $timeout Maximum statement time in seconds. null will use the
	 *   default specified by the APP_QUERY_TIMEOUT constant. -1 will set no timeout.
	 * @return ResultStatement
	 */
	public function executeReplicaQueryWithTypes(
		string $dbName,
		string $sql,
		array $params = [],
		array $types = [],
		?int $timeout = null
	): ResultStatement {
		try {
			$sql = $this->getQueryTimeoutClause( $timeout ) . $sql;
			return $this->replicasClient->getConnection( $dbName )->executeQuery( $sql, $params, $types );
		} catch ( DriverException $e ) {
			$this->handleDriverError( $e, $timeout );
		}
	}

	/**
	 * Execute a query using the projects connection, handling certain Exceptions.
	 * @param string $dbName
	 * @param string $sql
	 * @param array $params Parameters to bound to the prepared query.
	 * @param int|null $timeout Maximum statement time in seconds. null will use the
	 *   default specified by the APP_QUERY_TIMEOUT constant. -1 will set no timeout.
	 * @return ResultStatement
	 */
	public function executeReplicaQuery(
		string $dbName,
		string $sql,
		array $params = [],
		?int $timeout = null
	): ResultStatement {
		return $this->executeReplicaQueryWithTypes( $dbName, $sql, $params, [], $timeout );
	}

	/**
	 * Execute a query using the projects connection, handling certain Exceptions.
	 * @param QueryBuilder $qb
	 * @param int|null $timeout Maximum statement time in seconds. null will use the
	 *   default specified by the APP_QUERY_TIMEOUT constant. -1 will set no timeout.
	 * @return ResultStatement
	 * @throws DriverException
	 * @throws DBALException
	 */
	public function executeQueryBuilder( QueryBuilder $qb, ?int $timeout = null ): ResultStatement {
		try {
			$sql = $this->getQueryTimeoutClause( $timeout ) . $qb->getSQL();
			return $qb->getConnection()
				->executeQuery( $sql, $qb->getParameters(), $qb->getParameterTypes() );
		} catch ( DriverException $e ) {
			$this->handleDriverError( $e, $timeout );
		}
	}

	/**
	 * Special handling of some DriverExceptions, otherwise original Exception is thrown.
	 * @param DriverException $e
	 * @param int|null $timeout Timeout value, if applicable. This is passed to the i18n message.
	 * @throws ServiceUnavailableHttpException
	 * @throws DriverException
	 */
	private function handleDriverError( DriverException $e, ?int $timeout ): void {
		// If no value was passed for the $timeout, it must be the default.
		if ( $timeout === null ) {
			$timeout = self::APP_QUERY_TIMEOUT;
		}

		if ( $e->getErrorCode() === 1226 ) {
			throw new ServiceUnavailableHttpException( 30, 'error-service-overload', null, 503 );
		} elseif ( in_array( $e->getErrorCode(), [ 1969, 2006 ] ) ) {
			throw new HttpException( 504, 'error-query-timeout', null, [], $timeout );
		} else {
			throw $e;
		}
	}

	/**
	 * Set the maximum statement time on the MySQL engine.
	 * @param int|null $timeout In seconds. null will use the default specified by
	 *     the APP_QUERY_TIMEOUT constant. -1 will not set a timeout.
	 * @return string The SQL fragment to prepended to the query.
	 */
	public function getQueryTimeoutClause( ?int $timeout = null ): string {
		if ( $timeout === -1 ) {
			return '';
		}

		if ( $timeout === null ) {
			$timeout = self::APP_QUERY_TIMEOUT;
		}

		return "SET STATEMENT max_statement_time = $timeout FOR\n";
	}
}
