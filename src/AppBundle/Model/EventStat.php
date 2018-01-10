<?php
/**
 * This file contains only the EventStat class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * An EventStat is a user who organizes a program.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event_stat",
 *     indexes={
 *         @ORM\Index(name="es_metrics", columns={"es_event_id"}),
 *         @ORM\Index(name="es_event", columns={"es_event_id", "es_metric"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="es_event_metric", columns={"es_event_id", "es_metric"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class EventStat
{
    const METRIC_TYPES = [
        'new-editors',
        'retention',
        'pages-created',
        'pages-improved',
    ];

    /**
     * @ORM\Id
     * @ORM\Column(name="es_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID for the statistic.
     */
    protected $id;

    /**
     * Many EventStats belong to one Event.
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="stats")
     * @ORM\JoinColumn(name="es_event_id", referencedColumnName="event_id", nullable=false)
     * @var Event Event this EventStat applies to.
     */
    protected $event;

    /**
     * @ORM\Column(name="es_metric", type="string", length=32)
     * @var string Name of the event metric, such as 'retention', 'pages-created', 'pages-improved'.
     */
    protected $metric;

    /**
     * @ORM\Column(name="es_value", type="integer", options={"default":0})
     * @var int Value of the associated metric.
     */
    protected $value;

    /**
     * Event constructor.
     * @param int $event Event the statistic applies to.
     * @param string $metric Name of event metric, e.g. 'retention', 'pages-created', 'pages-improved'.
     * @param mixed $value Value of the associated metric.
     */
    public function __construct(Event $event, $metric, $value)
    {
        $this->event = $event;
        $this->event->addStatistic($this);
        $this->setMetric($metric);
        $this->value = $value;
    }

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
     * Get the Event this EventStat applies to.
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
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
     * Get the value of the EventStat.
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
