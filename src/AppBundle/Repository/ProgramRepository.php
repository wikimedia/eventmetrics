<?php
/**
 * This file contains only the ProgramRepository class.
 */

namespace AppBundle\Repository;

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

        $metrics = array_merge($eventMetrics, $eventWikiMetrics);
        $uniqueMetrics = [];

        foreach ($metrics as $metric) {
            // For each $metric, the first element is the metric name,
            // and the second element is the offset value.
            if (!isset($metrics[$metric[0]])) {
                $uniqueMetrics[$metric[0]] = $metric[1];
            }
        }

        return $uniqueMetrics;
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
}
