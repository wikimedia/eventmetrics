<?php
/**
 * This file contains only the EventRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Event;
use DateInterval;
use DateTime;
use Doctrine\DBAL\Connection;

/**
 * This class supplies and fetches data for the Event class.
 * @codeCoverageIgnore
 */
class EventRepository extends Repository
{
    /** @var string Cache of inner revisions SQL, which is called multiple times. */
    private $revisionsInnerSql;

    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass()
    {
        return Event::class;
    }

    /**
     * Get the usernames of participants who are new editors,
     * relative to the time of the event.
     * @param  Event $event The Event in question.
     * @return string[] Usernames of new editors.
     */
    public function getNewEditors(Event $event)
    {
        $userIds = $event->getParticipantIds();
        $offset = Event::getAvailableMetrics()['new-editors'];
        $start = (new DateTime($event->getStart()->format('YmdHis')))
            ->sub(new DateInterval('P'.$offset.'D'))
            ->format('YmdHis');
        $end = $event->getEnd()->format('YmdHis');

        $conn = $this->getCentralAuthConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select('gu_name')
            ->from('globaluser')
            ->where('gu_id IN (:userIds)')
            ->andwhere("gu_registration BETWEEN :start AND :end")
            ->setParameter('userIds', $userIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        $ret = $this->executeQueryBuilder($rqb)->fetchAll();
        return array_column($ret, 'gu_name');
    }

    /**
     * Get the number of pages edited and created within the timeframe
     * and for the given users.
     * @param  string   $dbName Database name such as 'enwiki_p'.
     * @param  DateTime $start
     * @param  DateTime $end
     * @param  string[] $usernames
     * @return array With keys 'edited' and 'created'.
     */
    public function getNumPagesEdited($dbName, DateTime $start, DateTime $end, $usernames)
    {
        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');

        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        $revisionTable = $this->getTableName('revision');

        $rqb->select([
                'COUNT(DISTINCT(page_title)) AS edited',
                'IFNULL(SUM(CASE WHEN rev_parent_id = 0 THEN 1 ELSE 0 END), 0) AS created',
            ])
            ->from("$dbName.page")
            ->join("$dbName.page", "$dbName.$revisionTable", null, 'rev_page = page_id')
            ->where('page_namespace = 0')
            ->andwhere('rev_timestamp BETWEEN :start AND :end')
            ->andwhere('rev_user_text IN (:usernames)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);

        return $this->executeQueryBuilder($rqb)->fetch();
    }

    /**
     * Get database names of wikis attached to the global accounts
     * with the given usernames.
     * @param  string[] $usernames
     * @return string[]
     */
    public function getCommonWikis($usernames)
    {
        $conn = $this->getCentralAuthConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select("DISTINCT(CONCAT(lu_wiki, '_p')) AS dbname")
            ->from('localuser')
            ->where('lu_name IN (:usernames)')
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);

        $ret = $this->executeQueryBuilder($rqb)->fetchAll();
        return array_column($ret, 'dbname');
    }

    /**
     * Get the domain names of wikis within the given family where all
     * of the given users have made edits.
     * @param  string[] $usernames
     * @param  string $family
     * @return string[] Domain names in the format of lang.project, e.g. en.wiktionary
     */
    public function getCommonLangWikiDomains($usernames, $family)
    {
        $conn = $this->getCentralAuthConnection();
        $rqb = $conn->createQueryBuilder();
        // The 'lang' column is not always the same as the subdomain, so we use SUBSTRING on the 'url'.
        $rqb->select('DISTINCT(SUBSTRING(url, 9, LENGTH(url) - 12)) AS domain')
            ->from('localuser')
            ->join('localuser', 'meta_p.wiki', null, 'lu_wiki = dbname')
            ->where('family = :family')
            ->andWhere('lu_name IN (:usernames)')
            ->setParameter('family', $family)
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);

        $ret = $this->executeQueryBuilder($rqb)->fetchAll();
        return array_column($ret, 'domain');
    }

    /**
     * Get the usernames of users who met the retention threshold
     * for the given wiki.
     * @param string $dbName Database name.
     * @param DateTime $start Search only from this time moving forward.
     * @param string[] $usernames
     * @return string[]
     */
    public function getUsersRetained($dbName, DateTime $start, $usernames)
    {
        $start = $start->format('YmdHis');
        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        $revisionTable = $this->getTableName('revision');

        $rqb->select('DISTINCT(rev_user_text) AS username')
            ->from("$dbName.$revisionTable")
            ->where('rev_timestamp > :start')
            ->andwhere('rev_user_text IN (:usernames)')
            ->setParameter('start', $start)
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);
        $ret = $this->executeQueryBuilder($rqb)->fetchAll();

        return array_column($ret, 'username');
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
    public function getRevisions(Event $event, $offset = 0, $limit = 50, $count = false)
    {
        /** @var int TTL of cache, in seconds. */
        $cacheDuration = 300;

        // Check cache and return if it exists, unless the Event was recently updated,
        // in which case we'll want to invalidate the cache.
        $shouldUseCache = $event->getUpdated() !== null &&
            (int)$event->getUpdated()->format('U') < time() - $cacheDuration &&
            $this->getRedisConnection() !== null;
        $cacheKey = $this->getCacheKey(func_get_args(), 'revisions');
        if ($shouldUseCache && $this->getRedisConnection()->contains($cacheKey)) {
            return $this->getRedisConnection()->fetch($cacheKey);
        }

        $ret = $this->getRevisionsData($event, $offset, $limit, $count);

        // Cache for 5 minutes.
        if ($shouldUseCache) {
            $redis = $this->getRedisConnection();
            $redis->save($cacheKey, $ret, $cacheDuration);
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
     * @return int|string[] Count of revisions, or string array with keys 'id',
     *     'timestamp', 'page', 'wiki', 'username', 'summary'.
     */
    private function getRevisionsData(Event $event, $offset, $limit, $count)
    {
        $sql = 'SELECT '.($count ? 'COUNT(id)' : '*').' FROM ('.
                    $this->getRevisionsInnerSql($event)."
                ) a";

        if ($count === false) {
            $sql .= "\nORDER BY timestamp ASC";
        }
        if ($offset !== null) {
            $sql .= "\nLIMIT $offset, $limit";
        }

        $start = $event->getStart()->format('Ymd000000');
        $end = $event->getEnd()->format('Ymd235959');

        $stmt = $this->executeReplicaQuery($sql, [
            'startDate' => $start,
            'endDate' => $end,
        ]);

        if ($count === true) {
            return (int)$stmt->fetchColumn();
        } else {
            return $stmt->fetchAll();
        }
    }

    /**
     * Get the number of revisions that were part of the given Event.
     * @param Event $event
     * @return int
     */
    public function getNumRevisions(Event $event)
    {
        return $this->getRevisions($event, null, null, true);
    }

    /**
     * The inner SQL used when fetching revisions that are part of an Event.
     * @param Event $event
     * @return string
     */
    private function getRevisionsInnerSql(Event $event)
    {
        if (isset($this->revisionsInnerSql)) {
            return $this->revisionsInnerSql;
        }

        $eventWikiRepo = $this->em->getRepository('Model:EventWiki');
        $eventWikiRepo->setContainer($this->container);
        $sqlClauses = [];

        $revisionTable = $this->getTableName('revision');
        $pageTable = $this->getTableName('page');
        $usernames = $this->getUsernamesSql($event);

        foreach ($event->getWikis() as $wiki) {
            if ($wiki->isFamilyWiki()) {
                continue;
            }

            $dbName = $eventWikiRepo->getDbName($wiki);
            $domain = $wiki->getDomain();

            $sqlClauses[] = "SELECT rev_id AS 'id',
                    rev_timestamp AS 'timestamp',
                    REPLACE(page_title, '_', ' ') AS 'page',
                    rev_user_text AS 'username',
                    rev_comment AS 'summary',
                    '$domain' AS 'wiki'
                FROM $dbName.$revisionTable
                JOIN $dbName.$pageTable ON page_id = rev_page
                WHERE rev_user_text IN ($usernames)
                AND page_namespace = 0
                AND rev_timestamp BETWEEN :startDate AND :endDate";
        }

        $this->revisionsInnerSql = implode(' UNION ', $sqlClauses);
        return $this->revisionsInnerSql;
    }

    /**
     * Get usernames as need for an IN clause in the SQL. Have to do this hackiness
     * because you can't bind PARAM_STR_ARRAY in Doctrine. The usernames are fetched
     * from CentralAuth, so they are safe from SQL injection.
     * @param Event $event
     */
    private function getUsernamesSql(Event $event)
    {
        $userIds = $event->getParticipantIds();
        $usernames = array_column($this->getUsernamesFromIds($userIds), 'user_name');

        // Quote for raw SQL string.
        $usernames = array_map(function ($username) {
            return $this->getReplicaConnection()->quote($username, \PDO::PARAM_STR);
        }, $usernames);

        return ltrim(implode(',', $usernames), ',');
    }

    public function getJobStatus(Event $event)
    {
        $conn = $this->getGrantMetricsConnection();
        $rqb = $conn->createQueryBuilder();
        $eventId = $event->getId();

        $rqb->select('job_started')
            ->from('job')
            ->where("job_event_id = $eventId");

        $ret = $this->executeQueryBuilder($rqb, false)->fetch();
        return isset($ret['job_started']) ? (bool)$ret['job_started'] : null;
    }
}
