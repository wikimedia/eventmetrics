<?php
/**
 * This file contains only the Repository class.
 */

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * A Repository is responsible for retrieving data from wherever it lives
 * (databases, APIs, filesystems, etc.).
 *
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 *
 * @codeCoverageIgnore
 */
abstract class Repository extends EntityRepository
{
    /** @var Container The application's DI container. */
    protected $container;

    /** @var CacheItemPoolInterface The cache. */
    protected $cache;

    /** @var LoggerInterface The log. */
    protected $log;

    /** @var Stopwatch The stopwatch for time profiling. */
    protected $stopwatch;

    /** @var Connection The connection to the grantmetrics database. */
    private $grantmetricsConnection;

    /** @var Connection The database connection to the replicas. */
    private $replicaConnection;

    /** @var Connection The CentralAuth database connection. */
    private $centralAuthConnection;

    /** @var Connection The meta database connection. */
    private $metaConnection;

    /** @var EntityManager The Doctrine entity manager. */
    protected $em;

    /**
     * Create a new Repository with a null logger.
     * Each Repository should define getEntityClass(), which gets
     * passed into the parent EntityRepository construct.
     * @param EntityManager $em The Doctrine entity manager.
     */
    public function __construct(EntityManager $em)
    {
        $metadata = $em->getClassMetadata($this->getEntityClass());
        parent::__construct($em, $metadata);

        $this->em = $em;
        $this->log = new NullLogger();
    }

    /**
     * The name of the Entity class associated with the Repository
     * (such as Program, Organizer, etc.). This must be defined
     * in every Repository class.
     * @abstract
     * @return string
     */
    abstract public function getEntityClass();

    /**
     * Set the DI container and assign the cache, log, and
     * stopwatch adapters, which are accessed via the Container.
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
        $this->cache = $container->get('cache.app');
        $this->log = $container->get('logger');
        $this->stopwatch = $container->get('debug.stopwatch');
    }

    /**
     * Get the DI container.
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /***************
     * CONNECTIONS *
     ***************/

    /**
     * Get the database connection for the 'grantmetrics' database.
     * @return Connection
     */
    protected function getGrantmetricsConnection()
    {
        if (!$this->grantmetricsConnection instanceof Connection) {
            $this->grantmetricsConnection = $this->container
                ->get('doctrine')
                ->getManager('grantmetrics')
                ->getConnection();
        }
        return $this->grantmetricsConnection;
    }

    /**
     * Get the database connection for the replicas.
     * @return Connection
     */
    protected function getCentralAuthConnection()
    {
        if (!$this->centralAuthConnection instanceof Connection) {
            $this->centralAuthConnection = $this->getContainer()
                ->get('doctrine')
                ->getManager('centralauth')
                ->getConnection();
        }
        return $this->centralAuthConnection;
    }

    /**
     * Get the database connection for the replicas.
     * @return Connection
     */
    protected function getMetaConnection()
    {
        if (!$this->metaConnection instanceof Connection) {
            $this->metaConnection = $this->getContainer()
                ->get('doctrine')
                ->getManager('meta')
                ->getConnection();
        }
        return $this->metaConnection;
    }

    /**
     * Get the database connection for the replicas.
     * @return Connection
     */
    protected function getReplicaConnection()
    {
        if (!$this->replicaConnection instanceof Connection) {
            $this->replicaConnection = $this->container
                ->get('doctrine')
                ->getManager('replicas')
                ->getConnection();
        }
        return $this->replicaConnection;
    }

    /*************
     * USERNAMES *
     *************/

    /**
     * Get the global user IDs for mutiple users,
     * based on the central auth database.
     * @param  string[] $usernames Usernames to query for.
     * @return string[] with keys 'user_name' and 'user_id'.
     * FIXME: add caching.
     */
    public function getUserIdsFromNames($usernames)
    {
        $rqb = $this->getCentralAuthConnection()->createQueryBuilder();
        $rqb->select(['gu_name AS user_name', 'gu_id AS user_id'])
            ->from('globaluser')
            ->andWhere('gu_name IN (:usernames)')
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);
        return $this->executeQueryBuilder($rqb)->fetchAll();
    }

    /**
     * Get the global user ID for the given username.
     * @param  string $username
     * @return int|null
     */
    public function getUserIdFromName($username)
    {
        $ret = $this->getUserIdsFromNames([$username]);
        return isset($ret[0]['user_id']) ? $ret[0]['user_id'] : null;
    }

    /**
     * Get the usernames given multiple global user IDs,
     * based on the central auth database.
     * @param  int[] $userIds User IDs to query for.
     * @return string[] with keys 'user_name' and 'user_id'.
     * FIXME: add caching.
     */
    public function getUsernamesFromIds($userIds)
    {
        $rqb = $this->getCentralAuthConnection()->createQueryBuilder();
        $rqb->select(['gu_name AS user_name', 'gu_id AS user_id'])
            ->from('globaluser')
            ->andWhere('gu_id IN (:userIds)')
            ->setParameter('userIds', $userIds, Connection::PARAM_INT_ARRAY);
        return $this->executeQueryBuilder($rqb)->fetchAll();
    }

    /**
     * Get the username given the global user ID.
     * @param  int $userId
     * @return string|null
     */
    public function getUsernameFromId($userId)
    {
        $ret = $this->getUsernamesFromIds([$userId]);
        return isset($ret[0]['user_name']) ? $ret[0]['user_name'] : null;
    }

    /*****************
     * QUERY HELPERS *
     *****************/

    /**
     * Get the table name for use when querying the replicas. This automatically
     * appends _userindex if the 'database.replica.is_wikimedia' config option is set.
     * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
     * @param string $name Name of table.
     * @param string $suffix Suffix to use instead of _userindex.
     * @return string
     */
    protected function getTableName($name, $suffix = null)
    {
        $isWikimedia = (bool)$this->container
            ->getParameter('database.replica.is_wikimedia');

        if ($isWikimedia && $suffix !== null) {
            return $name.'_'.$suffix;
        }

        // For 'revision' and 'logging' tables (actually views) on the WMF replicas,
        // use the indexed versions (that have some rows hidden, e.g. for revdeleted users).
        $isLoggingOrRevision = in_array($name, ['revision', 'logging', 'archive']);
        if ($isWikimedia && $isLoggingOrRevision) {
            $name = $name.'_userindex';
        }

        return $name;
    }

    /**
     * Execute a query using the projects connection, handling certain Exceptions.
     * @param string $sql
     * @param array $params Parameters to bound to the prepared query.
     * @param int|null $timeout Maximum statement time in seconds. null will use the
     *   default specified by the app.query_timeout config parameter.
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function executeReplicaQuery($sql, $params = [], $timeout = null)
    {
        try {
            $this->setQueryTimeout($timeout);
            return $this->getReplicaConnection()->executeQuery($sql, $params);
        } catch (DriverException $e) {
            return $this->handleDriverError($e, $timeout);
        }
    }

    /**
     * Execute a query using the projects connection, handling certain Exceptions.
     * @param QueryBuilder $qb
     * @param int $timeout Maximum statement time in seconds. null will use the
     *   default specified by the app.query_timeout config parameter.
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function executeQueryBuilder(QueryBuilder $qb, $timeout = null)
    {
        try {
            $this->setQueryTimeout($timeout);
            return $qb->execute();
        } catch (DriverException $e) {
            return $this->handleDriverError($e, $timeout);
        }
    }

    /**
     * Special handling of some DriverExceptions, otherwise original Exception is thrown.
     * @param DriverException $e
     * @param int $timeout Timeout value, if applicable. This is passed to the i18n message.
     * @throws ServiceUnavailableHttpException
     * @throws DriverException
     */
    private function handleDriverError(DriverException $e, $timeout)
    {
        // If no value was passed for the $timeout, it must be the default.
        if ($timeout === null) {
            $timeout = $this->container->getParameter('app.query_timeout');
        }

        if ($e->getErrorCode() === 1226) {
            throw new ServiceUnavailableHttpException(30, 'error-service-overload', null, 503);
        } elseif ($e->getErrorCode() === 1969) {
            throw new HttpException(504, 'error-query-timeout', null, [], $timeout);
        } else {
            throw $e;
        }
    }

    /**
     * Set the maximum statement time on the MySQL engine.
     * @param int|null $timeout In seconds. null will use the default
     *   specified by the app.query_timeout config parameter.
     */
    public function setQueryTimeout($timeout = null)
    {
        // Scrutinizer doesn't use MariaDB, and/or queries might for some reason take really long.
        if (!(bool)$this->container->getParameter('database.replica.is_wikimedia')) {
            return;
        }

        if ($timeout === null) {
            $timeout = $this->container->getParameter('app.query_timeout');
        }
        $sql = "SET max_statement_time = $timeout";
        $this->getReplicaConnection()->executeQuery($sql);
    }
}
