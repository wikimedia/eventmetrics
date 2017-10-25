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
class EventStat extends Model
{
    const METRIC_TYPES = [
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
     * @var string Name of the event metric, such 'retention', 'pages-created', 'pages-improved'.
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
        $this->assignMetric($metric, $value);
    }

    /**
     * Assign a metric and value to the class, throwing an exception
     * if it is of an unknown type.
     * @param  string $metric
     * @param  mixed $value
     */
    private function assignMetric($metric, $value)
    {
        if (!in_array($metric, self::METRIC_TYPES)) {
            throw new InvalidArgumentException(
                "'metric' must be of type: ".implode(', ', self::METRIC_TYPES)
            );
        }
        $this->metric = $metric;
        $this->value = $value;
    }

    /**
     * Get the Event this EventStat applies to.
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Get the metric type of this EventStat.
     */
    public function getMetric()
    {
        return $this->metric;
    }

    /**
     * Get the value of the EventStat.
     */
    public function getValue()
    {
        return $this->value;
    }
}
