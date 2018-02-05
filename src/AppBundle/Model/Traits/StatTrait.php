<?php
/**
 * This file contains only the StatTrait trait.
 */

namespace AppBundle\Model\Traits;

use InvalidArgumentException;

/**
 * The StatTrait contains shared logic between the EventStat
 * and EventWikiStat models.
 */
trait StatTrait
{
    /**
     * Assign the metric to the class instance, throwing an exception
     * if it is of an unknown type.
     * @param string $metric
     */
    private function setMetric($metric)
    {
        if (!in_array($metric, self::METRIC_TYPES)) {
            throw new InvalidArgumentException(
                "'metric' must be of type: ".implode(', ', self::METRIC_TYPES)
            );
        }
        $this->metric = $metric;
    }

    /**
     * Update the value associated with the EventStat.
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Get the metric type of this EventStat.
     * @return string
     */
    public function getMetric()
    {
        return $this->metric;
    }

    /**
     * Get the metric offset for this EventWikiStat,
     * such as the number of days for the retention.
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Get the value of the EventWikiStat.
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get valid types of metrics.
     * @static
     * @return string[]
     * @codeCoverageIgnore
     */
    public static function getMetricTypes()
    {
        return self::METRIC_TYPES;
    }
}
