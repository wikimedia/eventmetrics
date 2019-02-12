<?php
/**
 * This file contains only the EventStatTrait trait.
 */

declare(strict_types=1);

namespace AppBundle\Model\Traits;

use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * The EventStatTrait contains methods dealing with statistics of a specific Event. This trait is chiefly to extract out
 * logic and move it to a dedicated file for readability (or show me the correct way to make that file less massive :).
 *
 * These are here to assist IDE inspections, since the properties are defined in Event.
 * @property ArrayCollection|EventStat[] $stats
 * @property ArrayCollection|EventWiki[] $wikis
 * @property DateTime $updated
 */
trait EventStatTrait
{
    /**
     * Get statistics about this Event.
     * @return ArrayCollection|EventStat[]
     */
    public function getStatistics(): Collection
    {
        return $this->stats;
    }

    /**
     * Get the statistic about this Event with the given metric.
     * @param string $metric Name of metric, one of EventStat::METRIC_TYPES.
     * @return EventStat|null Null if no EventStat with given metric was found.
     */
    public function getStatistic(string $metric): ?EventStat
    {
        /** @var ArrayCollection $ewStats of EventStats. */
        $ewStats = $this->stats->filter(function (EventStat $stat) use ($metric) {
            return $stat->getMetric() === $metric;
        });
        return $ewStats->count() > 0 ? $ewStats->first() : null;
    }

    /**
     * Add an EventStat to this Event.
     * @param EventStat $eventStat
     */
    public function addStatistic(EventStat $eventStat): void
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
    public function removeStatistic(EventStat $eventStat): void
    {
        if (!$this->stats->contains($eventStat)) {
            return;
        }
        $this->stats->removeElement($eventStat);
    }

    /**
     * Clear all associated statistics, including EventWikiStats, and set the updated attribute to null.
     */
    public function clearStatistics(): void
    {
        $this->stats->clear();
        $this->setUpdated(null);

        foreach ($this->wikis->getIterator() as $wiki) {
            $wiki->clearStatistics();
        }
    }

    /**
     * Get the metric types available to this event, based on associated wikis, and their default offset values.
     * @param string|null $family Only return metrics available to given wiki family.
     * @return mixed[]
     */
    public function getAvailableMetrics(?string $family = null): array
    {
        $metricMap = self::WIKI_FAMILY_METRIC_MAP;

        // Start with metrics available to all wiki families.
        $metricKeys = $metricMap['*'];

        foreach ($this->wikis as $wiki) {
            $isFamily = null === $family ? true : $wiki->getFamilyName() === $family;

            if ($isFamily && isset($metricMap[$wiki->getFamilyName()])) {
                $metricKeys = array_merge(
                    $metricKeys,
                    $metricMap[$wiki->getFamilyName()]
                );
            }
        }

        // Return as associative array with the offsets as the values.
        return array_filter(self::AVAILABLE_METRICS, function ($offset, $metric) use ($metricKeys): bool {
            return in_array($metric, $metricKeys);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get all metrics available to all events, regardless of associated wikis.
     * @return string[]
     */
    public static function getAllAvailableMetrics(): array
    {
        return self::AVAILABLE_METRICS;
    }

    /**
     * Get the key names of the metrics that should be visible in the interface.
     * @return string[]
     */
    public static function getVisibleMetrics(): array
    {
        return self::VISIBLE_METRICS;
    }

    /**
     * Get the date of the last time the EventStat's were refreshed.
     * @see self::getUpdatedUTC() if you need to use the datestamp in an SQL query.
     * @return DateTime|null
     */
    public function getUpdated(): ?DateTime
    {
        return $this->updated;
    }

    /**
     * Get the updated at value as UTC. This is what should be used in SQL queries.
     * @return DateTime
     */
    public function getUpdatedUTC(): DateTime
    {
        $this->updated->setTimezone(new DateTimeZone($this->timezone));
        return new DateTime(
            $this->updated->format('Y-m-d H:i:s'),
            new DateTimeZone('UTC')
        );
    }

    /**
     * Set the 'update' attribute, to be set after EventStats have been refreshed.
     * @param DateTime|null $datestamp
     */
    public function setUpdated(?DateTime $datestamp): void
    {
        $this->updated = $datestamp;
    }
}
