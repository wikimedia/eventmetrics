<?php
/**
 * This file contains only the EventRepository class.
 */

declare(strict_types=1);

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
    public function getEntityClass(): string
    {
        return Event::class;
    }

    /**
     * Get the usernames of participants who are new editors, relative to the time of the event.
     * @param Event $event The Event in question.
     * @return string[] Usernames of new editors.
     */
    public function getNewEditors(Event $event): array
    {
        $userIds = $event->getParticipantIds();
        $offset = Event::getAllAvailableMetrics()['new-editors'];
        $start = (new DateTime($event->getStart()->format('YmdHis')))
            ->sub(new DateInterval('P'.$offset.'D'))
            ->format('YmdHis');
        $end = $event->getEnd()->format('YmdHis');

        $conn = $this->getCentralAuthConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select('gu_name')
            ->from('globaluser')
            ->where('gu_id IN (:userIds)')
            ->andWhere("gu_registration BETWEEN :start AND :end")
            ->setParameter('userIds', $userIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        $ret = $this->executeQueryBuilder($rqb)->fetchAll();
        return array_column($ret, 'gu_name');
    }

    /**
     * Get the number of pages edited and created within the timeframe and for the given users.
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param DateTime $start
     * @param DateTime $end
     * @param string[] $usernames
     * @param string[] $categoryTitles Only search within given categories.
     * @return int[] With keys 'edits', 'edited' and 'created'.
     */
    public function getEditStats(
        string $dbName,
        DateTime $start,
        DateTime $end,
        array $usernames = [],
        array $categoryTitles = []
    ): array {
        if (empty($usernames) && empty($categoryTitles)) {
            // FIXME: This should throw an Exception or something so we can print an error message.
            return [
                'edits' => 0,
                'edited' => 0,
                'created' => 0,
            ];
        }

        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');

        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        $revisionTable = $this->getTableName('revision');

        $rqb->select([
                'COUNT(*) AS edits',
                'COUNT(DISTINCT(rev_page)) AS edited',
                'IFNULL(SUM(CASE WHEN rev_parent_id = 0 THEN 1 ELSE 0 END), 0) AS created',
            ])
            ->from("$dbName.$revisionTable")
            ->join("$dbName.$revisionTable", "$dbName.page", 'page_rev', 'page_id = rev_page');

        if (count($categoryTitles) > 0) {
            $rqb->join("$dbName.$revisionTable", "$dbName.categorylinks", 'category_rev', 'cl_from = rev_page')
                ->where('cl_to IN (:categoryTitles)');
        }

        $rqb->andWhere('page_namespace = 0')
            ->andWhere('rev_timestamp BETWEEN :start AND :end');

        if (count($usernames) > 0) {
            $rqb->andWhere($rqb->expr()->in('rev_user_text', ':usernames'))
                ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);
        }

        $rqb->setParameter('start', $start)
            ->setParameter('end', $end);

        if (count($categoryTitles) > 0) {
            $rqb->setParameter('categoryTitles', $categoryTitles, Connection::PARAM_STR_ARRAY);
        }

        return $this->executeQueryBuilder($rqb)->fetch();
    }

    /**
     * Get the number of files uploaded in the given time period by given users.
     * @param DateTime $start
     * @param DateTime $end
     * @param string[] $usernames
     * @return int
     */
    public function getFilesUploadedCommons(DateTime $start, DateTime $end, array $usernames): int
    {
        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');

        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        $rqb->select(['COUNT(DISTINCT(img_name)) AS count'])
            ->from('commonswiki_p.image')
            ->where('img_timestamp BETWEEN :start AND :end')
            ->andWhere('img_user_text IN (:usernames)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);

        return (int)$this->executeQueryBuilder($rqb)->fetchColumn();
    }

    /**
     * Get the number of unique mainspace pages across all projects that are using files
     * uploaded by the given users that were uploaded during the given timeframe.
     * @param DateTime $start
     * @param DateTime $end
     * @param string[] $usernames
     * @return int
     */
    public function getFileUsage(DateTime $start, DateTime $end, array $usernames): int
    {
        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');

        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        $rqb->select(['COUNT(DISTINCT(img_name)) AS count'])
            ->from('commonswiki_p.globalimagelinks')
            ->join('commonswiki_p.globalimagelinks', 'commonswiki_p.image', null, 'gil_to = img_name')
            ->where('img_timestamp BETWEEN :start AND :end')
            ->andWhere('img_user_text IN (:usernames)')
            ->andWhere('gil_page_namespace_id = 0')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);

        return (int)$this->executeQueryBuilder($rqb)->fetchColumn();
    }

    /**
     * Get database names of wikis attached to the global accounts with the given usernames.
     * @param string[] $usernames
     * @return string[]
     */
    public function getCommonWikis(array $usernames): array
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
     * @param string[] $usernames
     * @param string $family
     * @return string[] Domain names in the format of lang.project, e.g. en.wiktionary
     */
    public function getCommonLangWikiDomains(array $usernames, string $family): array
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
    public function getUsersRetained(string $dbName, DateTime $start, array $usernames): array
    {
        $start = $start->format('YmdHis');
        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        $revisionTable = $this->getTableName('revision');

        $rqb->select('DISTINCT(rev_user_text) AS username')
            ->from("$dbName.$revisionTable")
            ->where('rev_timestamp > :start')
            ->andWhere('rev_user_text IN (:usernames)')
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
    public function getRevisions(Event $event, ?int $offset = 0, ?int $limit = 50, bool $count = false)
    {
        /** @var int $cacheDuration TTL of cache, in seconds. */
        $cacheDuration = 300;

        // Check cache and return if it exists, unless the Event was recently updated,
        // in which case we'll want to invalidate the cache.
        $shouldUseCache = null !== $event->getUpdated() &&
            (int)$event->getUpdated()->format('U') < time() - $cacheDuration &&
            null !== $this->getRedisConnection();
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
    private function getRevisionsData(Event $event, ?int $offset, ?int $limit, bool $count)
    {
        $innerSql = $this->getRevisionsInnerSql($event);

        if ('' === trim($innerSql)) {
            // No wikis were queried.
            return true === $count ? 0 : [];
        }

        $sql = 'SELECT '.($count ? 'COUNT(id)' : '*')." FROM ($innerSql) a";

        if (false === $count) {
            $sql .= "\nORDER BY timestamp ASC";
        }
        if (null !== $offset) {
            $sql .= "\nLIMIT $offset, $limit";
        }

        $start = $event->getStart()->format('Ymd000000');
        $end = $event->getEnd()->format('Ymd235959');

        $stmt = $this->executeReplicaQuery($sql, [
            'startDate' => $start,
            'endDate' => $end,
        ]);

        if (true === $count) {
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
    public function getNumRevisions(Event $event): int
    {
        return $this->getRevisions($event, null, null, true);
    }

    /**
     * The inner SQL used when fetching revisions that are part of an Event.
     * @param Event $event
     * @return string
     */
    private function getRevisionsInnerSql(Event $event): string
    {
        if (isset($this->revisionsInnerSql)) {
            return $this->revisionsInnerSql;
        }

        /** @var EventWikiRepository $eventWikiRepo */
        $eventWikiRepo = $this->em->getRepository('Model:EventWiki');
        $eventWikiRepo->setContainer($this->container);

        /** @var EventCategoryRepository $catRepo */
        $catRepo = $this->em->getRepository('Model:EventCategory');
        $catRepo->setContainer($this->container);

        $sqlClauses = [];

        $revisionTable = $this->getTableName('revision');
        $pageTable = $this->getTableName('page');
        $usernames = $this->getUsernamesSql($event);

        foreach ($event->getWikis() as $wiki) {
            if ($wiki->isFamilyWiki()) {
                continue;
            }

            $domain = $wiki->getDomain();
            $dbName = $eventWikiRepo->getDbNameFromDomain($domain);

            $catJoin = '';
            $catWhere = '';
            $catIds = $event->getCategoryIdsForWiki($wiki);
            if (count($catIds) > 0) {
                $catJoin = "INNER JOIN $dbName.categorylinks ON cl_from = rev_page";
                $categoryTitles = implode(',', array_map(function ($title) {
                    return $this->getReplicaConnection()->quote($title, \PDO::PARAM_STR);
                }, $event->getCategoryTitlesForWiki($wiki)));
                $catWhere = "AND cl_to IN ($categoryTitles)";
            }

            $nsClause = 'commonswiki_p' === $dbName
                ? '6 AND rev_parent_id = 0' // Only creations of File pages.
                : '0';

            $sqlClauses[] = "SELECT rev_id AS 'id',
                    rev_timestamp AS 'timestamp',
                    REPLACE(page_title, '_', ' ') AS 'page',
                    page_namespace AS namespace,
                    rev_user_text AS 'username',
                    comment_text AS 'summary',
                    '$domain' AS 'wiki'
                FROM $dbName.$revisionTable
                INNER JOIN $dbName.$pageTable ON page_id = rev_page
                LEFT OUTER JOIN $dbName.comment ON rev_comment_id = comment_id
                $catJoin
                WHERE rev_user_text IN ($usernames)
                AND page_namespace = $nsClause
                AND rev_timestamp BETWEEN :startDate AND :endDate
                $catWhere";
        }

        $this->revisionsInnerSql = implode(' UNION ', $sqlClauses);
        return $this->revisionsInnerSql;
    }

    /**
     * Get usernames as need for an IN clause in the SQL. Have to do this hackiness
     * because you can't bind PARAM_STR_ARRAY in Doctrine. The usernames are fetched
     * from CentralAuth, so they are safe from SQL injection.
     * @param Event $event
     * @return string
     */
    private function getUsernamesSql(Event $event): string
    {
        $userIds = $event->getParticipantIds();
        $usernames = array_column($this->getUsernamesFromIds($userIds), 'user_name');

        // Quote for raw SQL string.
        $usernames = array_map(function ($username) {
            return $this->getReplicaConnection()->quote($username, \PDO::PARAM_STR);
        }, $usernames);

        return ltrim(implode(',', $usernames), ',');
    }

    /**
     * Get the status of the existing job for this event, if any.
     * @param Event $event
     * @return bool|null true if job has been started, false if queued, null if nonexistent.
     */
    public function getJobStatus(Event $event): ?bool
    {
        $conn = $this->getEventMetricsConnection();
        $rqb = $conn->createQueryBuilder();
        $eventId = $event->getId();

        $rqb->select('job_started')
            ->from('job')
            ->where("job_event_id = $eventId");

        $ret = $this->executeQueryBuilder($rqb, -1)->fetch();
        return isset($ret['job_started']) ? (bool)$ret['job_started'] : null;
    }
}
