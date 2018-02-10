<?php
/**
 * This file contains only the EventRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Event;
use Doctrine\DBAL\Connection;
use DateTime;

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
     * Get the number of participants who are new editors,
     * relative to the time of the event.
     * @param  Event $event The Event in question.
     * @return int Number of new editors.
     */
    public function getNumNewEditors(Event $event)
    {
        $userIds = $event->getParticipantIds();
        $start = $event->getStart()->format('YmdHis');
        $end = $event->getEnd()->format('YmdHis');

        $conn = $this->getCentralAuthConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select('COUNT(gu_id)')
            ->from('globaluser')
            ->where('gu_id IN (:userIds)')
            ->andwhere('gu_registration BETWEEN DATE_SUB(:start, INTERVAL 15 DAY) AND :end')
            ->setParameter('userIds', $userIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('start', $start)
            ->setParameter('end', $end);
        $stmt = $rqb->execute();

        return $stmt->fetchColumn(0);
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

        return $rqb->execute()->fetch();
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
        $stmt = $rqb->execute();

        return array_column($stmt->fetchAll(), 'dbname');
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
        $stmt = $rqb->execute();

        return array_column($stmt->fetchAll(), 'username');
    }

    /**
     * Get raw revisions that were part of the given Event.
     * @param Event $event
     * @param int $offset Number of rows to offset, used for pagination.
     * @param int $limit Number of rows to fetch.
     * @param bool $count Whether to get a COUNT instead of the actual revisions.
     * @return int|string[] Count of revisions, or string array with keys 'id',
     *     'timestamp', 'page', 'wiki', 'username', 'summary'.
     */
    public function getRevisions(Event $event, $offset = 0, $limit = 50, $count = false)
    {
        $conn = $this->getReplicaConnection();

        // Have to do this hackiness because you can't bind PARAM_STR_ARRAY in Doctrine.
        // The usernames are fetched from CentralAuth, so they are safe from SQL injection.
        $usernames = '';
        foreach ($event->getParticipantNames() as $username) {
            $usernames .= ','.$conn->quote($username, \PDO::PARAM_STR);
        }
        $usernames = ltrim($usernames, ',');

        $sql = 'SELECT '.($count ? 'COUNT(id)' : '*').' FROM ('.
                    $this->getRevisionsInnerSql($event, $usernames)."
                ) a ";
        if ($count === false) {
            $sql .= "ORDER BY timestamp DESC
                     LIMIT $offset, $limit";
        }

        $start = $event->getStart()->format('Ymd000000');
        $end = $event->getEnd()->format('Ymd235959');

        $resultQuery = $conn->prepare($sql);
        $resultQuery->bindParam('startDate', $start);
        $resultQuery->bindParam('endDate', $end);
        $resultQuery->execute();

        if ($count === true) {
            return (int)$resultQuery->fetchColumn();
        } else {
            return $resultQuery->fetchAll();
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
     * @param  Event $event
     * @param  string $usernames Quoted and comma-separated.
     * @return string
     */
    private function getRevisionsInnerSql(Event $event, $usernames)
    {
        if (isset($this->revisionsInnerSql)) {
            return $this->revisionsInnerSql;
        }

        $eventWikiRepo = $this->em->getRepository('Model:EventWiki');
        $eventWikiRepo->setContainer($this->container);
        $sqlClauses = [];

        $revisionTable = $this->getTableName('revision');
        $pageTable = $this->getTableName('page');

        foreach ($event->getWikis() as $wiki) {
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
}
