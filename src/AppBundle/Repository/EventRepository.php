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
     * Get the database names of the EventWiki's belonging to the Event.
     * @param  Event $event
     * @return string[]
     */
    public function getDbNames(Event $event)
    {
        $projectUrls = array_map(function ($eventWiki) {
            return 'https://'.$eventWiki->getDomain().'.org';
        }, $event->getWikis()->toArray());

        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(["CONCAT(dbname, '_p') AS dbname"])
            ->from('wiki')
            ->where('url IN (:projectUrls)')
            ->setParameter('projectUrls', $projectUrls, Connection::PARAM_STR_ARRAY);
        $stmt = $rqb->execute();
        return array_column($stmt->fetchAll(), 'dbname');
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
                'SUM(CASE WHEN rev_parent_id = 0 THEN 1 ELSE 0 END) AS created',
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
}
