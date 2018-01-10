<?php
/**
 * This file contains only the EventRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Event;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

/**
 * This class supplies and fetches data for the EventWiki class.
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

    // public function getNumPagesCreated(Event $event)
    // {
    //     // TODO: Here we need to get the usernames

    //     // $userIds = $event->getParticipantIds();
    //     $start = $event->getStart()->format('YmdHis');
    //     $end = $event->getEnd()->format('YmdHis');

    //     $conn = $this->getCentralAuthConnection();
    //     $rqb = $conn->createQueryBuilder();
    //     $rqb->select('COUNT(page_title)')
    //         ->from('page')
    //         ->where('page_namespace = 0')
    //         ->andwhere('rev_parent_id = 0')
    //         ->andwhere('rev_timestamp BETWEEN :start AND :end')
    //         ->andwhere('rev_user_text IN ("MusikAnimal")')
    //         ->setParameter('start', $start)
    //         ->setParameter('end', $end);
    //     $stmt = $rqb->execute();

    //     return $stmt->fetchColumn(0);
    // }
}
