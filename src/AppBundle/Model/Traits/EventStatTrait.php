<?php
/**
 * This file contains only the EventStatTrait trait.
 */

namespace AppBundle\Model\Traits;

use AppBundle\Model\EventStat;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * The EventStatTrait contains methods dealing with statistics
 * of a specific Event. This class is chiefly to extract out
 * logic and move it to a dedicated file for readability.
 * (or show me the correct way to make that file less massive... :)
 */
trait EventStatTrait
{
    /**
     * Get statistics about this Event.
     * @return ArrayCollection|EventStat[]
     */
    public function getStatistics()
    {
        return $this->stats;
    }

    /**
     * Get the statistic about this Event with the given metric.
     * @param string $metric Name of metric, one of EventStat::METRIC_TYPES.
     * @return EventStat|null Null if no EventStat with given metric was found.
     */
    public function getStatistic($metric)
    {
        $ewStats = $this->stats->filter(function ($stat) use ($metric) {
            return $stat->getMetric() === $metric;
        });
        return $ewStats->count() > 0 ? $ewStats->first() : null;
    }

    /**
     * Add an EventStat to this Event.
     * @param EventStat $eventStat
     */
    public function addStatistic(EventStat $eventStat)
    {
        if ($this->stats->contains($eventStat)) {
            return;
        }
        $this->stats->add($eventStat);
    }

    /**
     * Remove an eventStat from this Event.
     * @param EventStat $eventStat
     */
    public function removeStatistic(EventStat $eventStat)
    {
        if (!$this->stats->contains($eventStat)) {
            return;
        }
        $this->stats->removeElement($eventStat);
    }

    /**
     * Clear all associated statistics, including EventWikiStats,
     * and set the updated attribute to null.
     */
    public function clearStatistics()
    {
        $this->stats->clear();
        $this->setUpdated(null);

        foreach ($this->wikis->toArray() as $wiki) {
            $wiki->clearStatistics();
        }
    }

    /**
     * Get the metric types available to this event, based on associated wikis,
     * and their default offset values.
     * @return array
     */
    public function getAvailableMetrics()
    {
        $metricMap = self::WIKI_FAMILY_METRIC_MAP;

        // Start with metrics available to all wiki families.
        $metricKeys = $metricMap['*'];

        foreach ($this->wikis as $wiki) {
            if (isset($metricMap[$wiki->getFamilyName()])) {
                $metricKeys = array_merge(
                    $metricKeys,
                    $metricMap[$wiki->getFamilyName()]
                );
            }
        }

        // Return as associative array with the offsets as the values.
        return array_filter(self::AVAILABLE_METRICS, function ($offset, $metric) use ($metricKeys) {
            return in_array($metric, $metricKeys);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get all metrics available to all events, regardless of associated wikis.
     * @return string[]
     */
    public static function getAllAvailableMetrics()
    {
        return self::AVAILABLE_METRICS;
    }

    /**
     * Get the date of the last time the EventStat's were refreshed.
     * @return DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Get the update at value adjusted with the Event's timezone.
     * @return DateTime
     */
    public function getUpdatedWithTimezone()
    {
        $this->updated->setTimezone(new DateTimeZone($this->timezone));
        return new DateTime(
            $this->updated->format('Y-m-d H:i:s'),
            new DateTimeZone('UTC')
        );
    }

    /**
     * Set the 'update' attribute, to be set after EventStats
     * have been refreshed.
     * @param DateTime|null $datestamp
     */
    public function setUpdated($datestamp)
    {
        $this->updated = $datestamp;
    }
}
