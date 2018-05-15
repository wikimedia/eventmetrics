<?php
/**
 * This file contains only the ProgramRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;

/**
 * This class supplies and fetches data for the Program class.
 * @codeCoverageIgnore
 */
class ProgramRepository extends Repository
{
    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass()
    {
        return Program::class;
    }

    /**
     * Get the unique metrics for this Program, across all Events.
     * This also uses the configured metrics in Event::getAllAvailableMetrics()
     * to determine what order the metrics should be presented.
     * @param  Program $program
     * @return string[]
     */
    public function getUniqueMetrics(Program $program)
    {
        $rqb = $this->getGrantmetricsConnection()->createQueryBuilder();

        $eventIds = $program->getEventIds();

        $eventMetrics = $rqb->select(['DISTINCT(es_metric), es_metric_offset'])
            ->from('event_stat')
            ->where('es_event_id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_NUM);
        $eventWikiMetrics = $this->getEventWikiMetrics($rqb, $eventIds);

        $mergedMetrics = array_merge($eventMetrics, $eventWikiMetrics);
        $availableMetrics = array_keys(Event::getAllAvailableMetrics());
        $metrics = [];

        // Use available metrics to build our array in the order we want them
        // to be shown in the interface.
        foreach ($availableMetrics as $metric) {
            foreach ($mergedMetrics as $row) {
                if ($row[0] === $metric && !isset($metrics[$row[0]])) {
                    // Keys are the metric name, values are the offset.
                    $metrics[$row[0]] = $row[1];
                }
            }
        }

        return $metrics;
    }

    /**
     * Get the names and offsets of all unique EventWikiMetrics
     * belonging to the Events with the given event IDs.
     * @param  QueryBuilder $rqb
     * @param  int[] $eventIds
     * @return array With metric names as the keys.
     */
    private function getEventWikiMetrics(QueryBuilder $rqb, array $eventIds)
    {
        $eventWikiIds = $rqb->select(['ew_id'])
            ->from('event_wiki')
            ->where('ew_event_id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);

        return $rqb->select(['DISTINCT(ews_metric), ews_metric_offset'])
            ->from('event_wiki_stat')
            ->where('ews_event_wiki_id IN (:eventWikiIds)')
            ->setParameter('eventWikiIds', $eventWikiIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_NUM);
    }

    /**
     * Get the number of participants of Events belonging to the Program.
     * @param Program $program
     * @return int
     */
    public function getNumParticipants(Program $program)
    {
        $rqb = $this->getGrantmetricsConnection()->createQueryBuilder();

        $eventIds = $program->getEventIds();

        // Don't run a query unless you need to.
        if (count($eventIds) === 0) {
            return 0;
        }

        return $rqb->select(['COUNT(DISTINCT(par_user_id))'])
            ->from('participant')
            ->where('par_event_id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchColumn();
    }
}
