<?php
/**
 * This file contains only the ProgramRepository class.
 */

declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\Event;
use AppBundle\Model\Program;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

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
    public function getEntityClass(): string
    {
        return Program::class;
    }

    /**
     * Get the unique metrics for this Program, across all Events.
     * This also uses the configured metrics in Event::getAllAvailableMetrics()
     * to determine what order the metrics should be presented.
     * @param Program $program
     * @return string[]
     */
    public function getUniqueMetrics(Program $program): array
    {
        $rqb = $this->getEventMetricsConnection()->createQueryBuilder();

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
     * Get the names and offsets of all unique EventWikiMetrics belonging to the Events with the given event IDs.
     * @param QueryBuilder $rqb
     * @param int[] $eventIds
     * @return mixed[] With metric names as the keys.
     */
    private function getEventWikiMetrics(QueryBuilder $rqb, array $eventIds): array
    {
        $eventWikiIds = $rqb->select(['DISTINCT(ew_id)'])
            ->from('event_wiki')
            ->where('ew_event_id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);

        // Sometimes Doctrine query builder isn't the way to go... The raw SQL here is dramatically faster.
        $sql = "SELECT DISTINCT(ews_metric), ews_metric_offset
                FROM event_wiki_stat
                WHERE ews_event_wiki_id IN (?)";
        $stmt = $this->getEventMetricsConnection()
            ->executeQuery($sql, [$eventWikiIds], [Connection::PARAM_INT_ARRAY]);
        return $stmt->fetchAll(\PDO::FETCH_NUM);
    }

    /**
     * Get the number of participants of Events belonging to the Program.
     * @param Program $program
     * @return int
     */
    public function getNumParticipants(Program $program): int
    {
        $rqb = $this->getEventMetricsConnection()->createQueryBuilder();

        $eventIds = $program->getEventIds();

        // Don't run a query unless you need to.
        if (0 === count($eventIds)) {
            return 0;
        }

        return (int)$rqb->select(['COUNT(DISTINCT(par_user_id))'])
            ->from('participant')
            ->where('par_event_id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchColumn();
    }
}
