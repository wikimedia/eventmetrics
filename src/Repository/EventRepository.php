<?php

declare( strict_types=1 );

namespace App\Repository;

use App\Model\Event;
use App\Model\EventWiki;
use DateInterval;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

/**
 * This class supplies and fetches data for the Event class.
 * @codeCoverageIgnore
 */
class EventRepository extends Repository {
	/** @var string[] Per-database cache of inner revisions SQL, which is called multiple times. */
	private array $revisionsInnerSql;

	public const PAGES_CREATED = 'created';
	public const PAGES_IMPROVED = 'improved';

	/**
	 * @param EntityManagerInterface $em
	 * @param CacheItemPoolInterface $cache
	 * @param ManagerRegistry $managerRegistry
	 * @param ReplicasClient $replicasClient
	 * @param EventWikiRepository $eventWikiRepo
	 */
	public function __construct(
		EntityManagerInterface $em,
		CacheItemPoolInterface $cache,
		ManagerRegistry $managerRegistry,
		ReplicasClient $replicasClient,
		protected EventWikiRepository $eventWikiRepo
	) {
		parent::__construct( $em, $cache, $managerRegistry, $replicasClient );
	}

	/**
	 * Class name of associated entity.
	 * Implements Repository::getEntityClass
	 * @return string
	 */
	public function getEntityClass(): string {
		return Event::class;
	}

	/**
	 * Get the usernames of participants who are new editors, relative to the time of the event.
	 * @param Event $event The Event in question.
	 * @param string[]|null $usernames Usernames of already known editors or null to use the list from the event
	 * @return string[] Usernames of new editors.
	 */
	public function getNewEditors( Event $event, ?array $usernames = null ): array {
		if ( $usernames === [] ) {
			return [];
		}

		$offset = Event::getAllAvailableMetrics()['new-editors'];
		$start = ( new DateTime( $event->getStartUTC()->format( 'YmdHis' ) ) )
			->sub( new DateInterval( 'P' . $offset . 'D' ) )
			->format( 'YmdHis' );
		$end = $event->getEnd()->format( 'YmdHis' );

		$conn = $this->getCentralAuthConnection();
		$rqb = $conn->createQueryBuilder();
		$rqb->select( 'gu_name' )
			->from( 'globaluser' )
			->where( "gu_registration BETWEEN :start AND :end" )
			->setParameter( 'start', $start )
			->setParameter( 'end', $end );

		if ( $usernames ) {
			$rqb->andWhere( 'gu_name IN (:usernames)' )
				->setParameter( 'usernames', $usernames, Connection::PARAM_STR_ARRAY );
		} else {
			$userIds = $event->getParticipantIds();
			$rqb->andWhere( 'gu_id IN (:userIds)' )
				->setParameter( 'userIds', $userIds, Connection::PARAM_STR_ARRAY );
		}

		$ret = $this->executeQueryBuilder( $rqb )->fetchAll();
		return array_column( $ret, 'gu_name' );
	}

	/**
	 * Get the number of edits made within the time frame and for the given users.
	 * @param string $dbName Database name such as 'enwiki_p'.
	 * @param int[] $pageIds
	 * @param DateTime $start
	 * @param DateTime $end
	 * @param int[] $actors
	 * @return int
	 */
	public function getTotalEditCount(
		string $dbName,
		array $pageIds,
		DateTime $start,
		DateTime $end,
		array $actors = []
	): int {
		$start = $start->format( 'YmdHis' );
		$end = $end->format( 'YmdHis' );

		$conn = $this->replicasClient->getConnection( $dbName );
		$rqb = $conn->createQueryBuilder();

		$revisionTable = $this->getTableName( 'revision' );

		$rqb->select( [ 'COUNT(*) AS total' ] )
			->from( "$dbName.$revisionTable" )
			->where( $rqb->expr()->in( 'rev_page', ':pageIds' ) )
			->andWhere( 'rev_timestamp BETWEEN :start AND :end' );

		if ( $actors ) {
			$rqb->andWhere( $rqb->expr()->in( 'rev_actor', ':actors' ) )
				->setParameter( 'actors', $actors, Connection::PARAM_INT_ARRAY );
		}

		$rqb->setParameter( 'pageIds', $pageIds, Connection::PARAM_INT_ARRAY )
			->setParameter( 'start', $start )
			->setParameter( 'end', $end );

		$result = $this->executeQueryBuilder( $rqb )->fetch();
		return (int)$result['total'];
	}

	/**
	 * Get the number of files uploaded that are currently being used in at least one article, across all wikis.
	 * @param string $dbName Database name such as 'enwiki_p'. For 'commonswiki_p' this will be global usage.
	 * @param int[] $pageIds
	 * @return int
	 */
	public function getUsedFiles(
		string $dbName,
		array $pageIds
	): int {
		$conn = $this->replicasClient->getConnection( $dbName );
		$rqb = $conn->createQueryBuilder();

		if ( $dbName === 'commonswiki_p' ) {
			$rqb->select( [ 'COUNT(DISTINCT(gil_to)) AS count' ] )
				->from( 'commonswiki_p.globalimagelinks' )
				->join(
					'commonswiki_p.globalimagelinks',
					'commonswiki_p.page',
					null,
					'gil_to = page_title AND page_namespace = 6 AND gil_page_namespace_id = 0'
				);
		} else {
			$rqb->select( [ 'COUNT(DISTINCT(il_to)) AS count' ] )
				->from( "$dbName.imagelinks" )
				->join(
					"$dbName.imagelinks",
					"$dbName.page",
					null,
					'il_to = page_title AND page_namespace = 6 AND il_from_namespace = 0'
				);
		}

		$rqb->where( 'page_id IN (:pageIds)' );
		$rqb->setParameter( 'pageIds', $pageIds, Connection::PARAM_STR_ARRAY );

		return (int)$this->executeQueryBuilder( $rqb )->fetchColumn();
	}

	/**
	 * Get the number of unique mainspace pages using the given file.
	 * @param string $dbName
	 * @param string $filename
	 * @return int
	 */
	public function getPagesUsingFile( string $dbName, string $filename ): int {
		$conn = $this->replicasClient->getConnection( $dbName );
		$rqb = $conn->createQueryBuilder();

		if ( $dbName === 'commonswiki_p' ) {
			$rqb->select( 'COUNT(DISTINCT(CONCAT(gil_wiki, gil_page)))' )
				->from( 'commonswiki_p.globalimagelinks' )
				->where( 'gil_to = :filename' )
				->andWhere( 'gil_page_namespace_id = 0' );
		} else {
			$rqb->select( 'COUNT(DISTINCT(il_from)) AS count' )
				->from( "$dbName.imagelinks" )
				->where( 'il_to = :filename' )
				->andWhere( 'il_from_namespace = 0' );
		}

		$rqb->setParameter( 'filename', $filename );

		return (int)$this->executeQueryBuilder( $rqb )->fetchColumn();
	}

	/**
	 * Get the mainspace pages across all projects that are using files
	 * uploaded by the given users that were uploaded during the given time frame.
	 * @param string $dbName Database name such as 'enwiki_p'. For 'commonswiki_p' this will be global usage.
	 * @param int[] $pageIds
	 * @return array[] Array containing arrays with keys 'dbName' and 'pageId'].
	 */
	public function getPagesUsingFiles(
		string $dbName,
		array $pageIds
	): array {
		$conn = $this->replicasClient->getConnection( $dbName );
		$rqb = $conn->createQueryBuilder();

		if ( $dbName === 'commonswiki_p' ) {
			$rqb->select( [ "CONCAT(gil_wiki, '_p') AS dbName", 'gil_page AS pageId' ] )
				->from( 'commonswiki_p.globalimagelinks' )
				->join( 'commonswiki_p.globalimagelinks', 'commonswiki_p.image', 'links_image', 'gil_to = img_name' )
				->join( 'links_image', 'commonswiki_p.page', 'image_page',
					'gil_to = page_title AND page_namespace = 6'
				)
				->where( 'gil_page_namespace_id = 0' )
				->andWhere( 'page_id IN (:pageIds)' );
		} else {
			$rqb->select( [ "'$dbName' AS dbName", 'il_from AS pageId' ] )
				->from( "$dbName.imagelinks" )
				->join( "$dbName.imagelinks", "$dbName.image", 'links_image', 'il_to = img_name' )
				->join( 'links_image', "$dbName.page", 'image_page', 'il_to = page_title AND page_namespace = 6' )
				->where( 'il_from_namespace = 0' )
				->andWhere( 'page_id IN (:pageIds)' );
		}

		$rqb->andWhere( 'page_id IN (:pageIds)' );
		$rqb->setParameter( 'pageIds', $pageIds, Connection::PARAM_STR_ARRAY );
		$rqb->groupBy( [ 'dbName', 'pageId' ] );

		return $this->executeQueryBuilder( $rqb )->fetchAll();
	}

	/**
	 * Get database names of wikis attached to the global accounts with the given usernames.
	 * @param string[] $usernames
	 * @return string[]
	 */
	public function getCommonWikis( array $usernames ): array {
		$conn = $this->getCentralAuthConnection();
		$rqb = $conn->createQueryBuilder();
		$rqb->select( "DISTINCT(CONCAT(lu_wiki, '_p')) AS dbname" )
			->from( 'localuser' )
			->where( 'lu_name IN (:usernames)' )
			->setParameter( 'usernames', $usernames, Connection::PARAM_STR_ARRAY );

		$ret = $this->executeQueryBuilder( $rqb )->fetchAll();
		return array_column( $ret, 'dbname' );
	}

	/**
	 * Get the domain names of wikis within the given family where all
	 * of the given users have made edits.
	 * @param string[] $usernames
	 * @param string $family
	 * @return string[] Domain names in the format of lang.project, e.g. en.wiktionary
	 */
	public function getCommonLangWikiDomains( array $usernames, string $family ): array {
		$conn = $this->getCentralAuthConnection();
		$rqb = $conn->createQueryBuilder();
		// The 'lang' column is not always the same as the subdomain, so we use SUBSTRING on the 'url'.
		$rqb->select( 'DISTINCT(SUBSTRING(url, 9, LENGTH(url) - 12)) AS domain' )
			->from( 'localuser' )
			->join( 'localuser', 'meta_p.wiki', null, 'lu_wiki = dbname' )
			->where( 'family = :family' )
			->andWhere( 'lu_name IN (:usernames)' )
			->setParameter( 'family', $family )
			->setParameter( 'usernames', $usernames, Connection::PARAM_STR_ARRAY );

		$ret = $this->executeQueryBuilder( $rqb )->fetchAll();
		return array_column( $ret, 'domain' );
	}

	/**
	 * Get the usernames of users who met the retention threshold
	 * for the given wiki.
	 * @param string $dbName Database name.
	 * @param DateTime $start Search only from this time moving forward.
	 * @param int[] $actors
	 * @return string[]
	 */
	public function getUsersRetained( string $dbName, DateTime $start, array $actors ): array {
		$start = $start->format( 'YmdHis' );
		$conn = $this->replicasClient->getConnection( $dbName );
		$rqb = $conn->createQueryBuilder();

		$revisionTable = $this->getTableName( 'revision' );

		$rqb->select( 'DISTINCT(actor_name) AS username' )
			->from( "$dbName.$revisionTable", 'r' )
			->join( 'r', "$dbName.actor", 'a', 'rev_actor = actor_id' )
			->where( 'rev_timestamp > :start' )
			->andWhere( 'rev_actor IN (:actors)' )
			->setParameter( 'start', $start )
			->setParameter( 'actors', $actors, Connection::PARAM_INT_ARRAY );
		$ret = $this->executeQueryBuilder( $rqb )->fetchAll();

		return array_column( $ret, 'username' );
	}

	/**
	 * Get raw revisions that were part of the given Event. Results are cached for 5 minutes.
	 * @param Event $event
	 * @param int|null $offset Number of rows to offset, used for pagination.
	 * @param int|null $limit Number of rows to fetch.
	 * @param bool $count Whether to get a COUNT instead of the actual revisions.
	 * @return int|string[] Count of revisions, or string array with keys 'id',
	 *     'timestamp', 'page', 'wiki', 'username', 'summary'.
	 */
	public function getRevisions( Event $event, ?int $offset = 0, ?int $limit = 50, bool $count = false ) {
		/** @var int $cacheDuration TTL of cache, in seconds. */
		$cacheDuration = 300;

		// Check cache and return if it exists, unless the Event was recently updated,
		// in which case we'll want to invalidate the cache.
		$shouldUseCache = $event->getUpdated() !== null &&
			(int)$event->getUpdated()->format( 'U' ) < time() - $cacheDuration;
		$cacheKey = $this->getCacheKey( func_get_args(), 'revisions' );
		if ( $shouldUseCache && $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$ret = $this->getRevisionsData( $event, $offset, $limit, $count );

		// Cache for 5 minutes.
		if ( $shouldUseCache ) {
			return $this->setCache( $cacheKey, $ret, 'PT5M' );
		}

		return $ret;
	}

	/**
	 * Method that actually runs the query to get raw revisions that were part of
	 * the given Event. Called by self::getRevisions().
	 * @param Event $event
	 * @param int|null $offset Number of rows to offset, used for pagination.
	 * @param int|null $limit Number of rows to fetch.
	 * @param bool $count Whether to get a COUNT instead of the actual revisions.
	 * @return int|mixed[] Count of revisions, or string array with keys 'id',
	 *     'timestamp', 'page', 'wiki', 'username', 'summary'.
	 */
	private function getRevisionsData( Event $event, ?int $offset, ?int $limit, bool $count ) {
		$revisionsData = ( $count ? 0 : [] );
		foreach ( $event->getWikis() as $wiki ) {
			// Family wikis are essentially placeholder EventWikis. They are not queryable by themselves.
			// An EventWiki may be invalid (exempt from stats generation) if there are no categories on it
			//   and no participants on the Event.
			if ( $wiki->isFamilyWiki() || !$wiki->isValid() ) {
				continue;
			}

			$innerSql = $this->getRevisionsInnerSql( $event, $wiki );
			if ( empty( $innerSql ) ) {
				continue;
			}

			$sql = 'SELECT ' . ( $count ? 'COUNT(id)' : '*' ) . " FROM ($innerSql) a";

			if ( $count === false ) {
				$sql .= "\nORDER BY timestamp ASC";
			}
			if ( $offset !== null ) {
				$sql .= "\nLIMIT $offset, $limit";
			}

			$start = $event->getStartUTC()->format( 'YmdHis' );
			$end = $event->getEndUTC()->format( 'YmdHis' );

			$dbName = $this->eventWikiRepo->getDbNameFromDomain( $wiki->getDomain() );
			$stmt = $this->executeReplicaQuery( $dbName, $sql, [
				'startDate' => $start,
				'endDate' => $end,
			] );
			if ( $count === true ) {
				$revisionsData += (int)$stmt->fetchColumn();
			} else {
				$revisionsData = array_merge( $revisionsData, $stmt->fetchAll() );
			}
		}
		if ( is_array( $revisionsData ) ) {
			usort( $revisionsData, static function ( $revA, $revB ) {
				return $revB['timestamp'] <=> $revA['timestamp'];
			} );
		}
		return $revisionsData;
	}

	/**
	 * Get the number of revisions that were part of the given Event.
	 * @param Event $event
	 * @return int
	 */
	public function getNumRevisions( Event $event ): int {
		return $this->getRevisions( $event, null, null, true );
	}

	/**
	 * The inner SQL used when fetching revisions that are part of an Event.
	 * NOTE: This method assumes page IDs are already stored on each EventWiki.
	 * @param Event $event
	 * @param EventWiki $wiki
	 * @return string
	 */
	private function getRevisionsInnerSql( Event $event, EventWiki $wiki ): string {
		$domain = $wiki->getDomain();
		$dbName = $this->eventWikiRepo->getDbNameFromDomain( $domain );

		if ( isset( $this->revisionsInnerSql[$dbName] ) ) {
			return $this->revisionsInnerSql[$dbName];
		}

		$revisionTable = $this->getTableName( 'revision' );
		$pageTable = $this->getTableName( 'page' );

		$userIds = $event->getParticipantIds();
		$usernames = array_column( $this->getUsernamesFromIds( $userIds ), 'user_name' );

		$pageIdsSql = implode( ',', array_merge( $wiki->getPages(), $wiki->getPagesFiles() ) );

		// Skip if there are no pages to query (otherwise `rev_page IN` clause will cause SQL error).
		if ( $pageIdsSql === '' ) {
			return '';
		}

		$actors = $this->getActorIdsFromUsernames( $dbName, $usernames );
		// The above function guarantees it returns only ints
		$actorList = ltrim( implode( ',', $actors ), ',' );

		$actorClause = $actorList === '' ? '' : "AND rev_actor IN ($actorList)";

		$sql = "SELECT rev_id AS 'id',
                rev_timestamp AS 'timestamp',
                REPLACE(page_title, '_', ' ') AS 'page',
                page_namespace AS namespace,
                actor_name AS 'username',
                IFNULL(comment_text, '') AS 'summary',
                '$domain' AS 'wiki'
            FROM $revisionTable
                INNER JOIN $pageTable ON page_id = rev_page
                LEFT OUTER JOIN comment ON rev_comment_id = comment_id
                JOIN actor ON rev_actor = actor_id
            WHERE page_is_redirect = 0
            $actorClause
            AND rev_page IN ($pageIdsSql)
            AND rev_timestamp BETWEEN :startDate AND :endDate";
		$this->revisionsInnerSql[$dbName] = $sql;
		return $this->revisionsInnerSql[$dbName];
	}

	/**
	 * Get the status of the existing job for this event, if any.
	 *
	 * @param Event $event
	 * @return bool|null true if job has been started, false if queued, null if nonexistent.
	 */
	public function getJobStatus( Event $event ): ?bool {
		$conn = $this->getEventMetricsConnection();
		$rqb = $conn->createQueryBuilder();
		$eventId = $event->getId();

		$rqb->select( 'job_started' )
			->from( 'job' )
			->where( "job_event_id = $eventId" );

		$ret = $this->executeQueryBuilder( $rqb, -1 )->fetch();
		return isset( $ret['job_started'] ) ? (bool)$ret['job_started'] : null;
	}

	/**
	 * Get the data needed for the Pages Created report.
	 * @param Event $event
	 * @param string[] $usernames
	 * @param string $type One of PAGES_* constants
	 * @return array
	 */
	public function getPagesData( Event $event, array $usernames, string $type ): array {
		$data = [];

		/** @var EventWiki $wiki */
		foreach ( $event->getWikis()->getIterator() as $wiki ) {
			$dbName = $this->eventWikiRepo->getDbNameFromDomain( $wiki->getDomain() );
			$actors = $this->getActorIdsFromUsernames( $dbName, $usernames );
			if ( self::PAGES_CREATED === $type ) {
				$wikiPages = $this->eventWikiRepo->getPagesCreatedData( $wiki, $actors );
			} else {
				$wikiPages = $this->eventWikiRepo->getPagesImprovedData( $wiki, $actors );
			}
			$data = array_merge( $data, $wikiPages );
		}

		// Sort by avg. pageviews.
		usort( $data, static function ( $a, $b ) {
			return $a['avgPageviews'] <=> $b['avgPageviews'];
		} );

		return $data;
	}
}
