<?php
/**
 * This file contains only the EventStat class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use AppBundle\Model\Traits\StatTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * An EventStat is a statistic about a specific event.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event_stat",
 *     indexes={
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
    /**
     * Shared methods between EventStat and EventWikiStat models.
     */
    use StatTrait;

    /**
     * Allowed metric types. Keys are the i18n key for the metric, and is what is stored in the database.
     * Values are the applicable wikis for that type of metric, with null meaning all wikis. These also serve as the
     * defaults when no EventStats are associated with an Event, based on the configured wiki(s).
     * @see StatTrait Shared methods that use this constant.
     */
    public const METRIC_TYPES = [
        'participants',
        'new-editors',
        'retention',
        'pages-using-files-pageviews-avg',

        // These are also stored on a per-wiki basis as a EventWikiStat
        'edits',
        'byte-difference',
        'pages-created',
        'pages-improved',
        'pages-created-pageviews',
        'pages-improved-pageviews-avg',
        'files-uploaded',
        'file-usage',
        'pages-using-files',

        // For Wikidata. These are the same metric as pages-created and -improved, but only stored per-event.
        'items-created',
        'items-improved',
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
     *   This should correspond with an i18n message.
     */
    protected $metric;

    /**
     * @ORM\Column(name="es_metric_offset", type="integer", nullable=true)
     * @var int Offset value for the metric, if applicable (e.g. num days retention).
     *   The logic for how this is used lives in ProcessEventCommand.
     */
    protected $offset;

    /**
     * @ORM\Column(name="es_value", type="integer", options={"default":0})
     * @var int Value of the associated metric.
     */
    protected $value;

    /**
     * EventStat constructor.
     * @param Event $event Event the statistic applies to.
     * @param string $metric Name of event metric, e.g. 'retention', 'pages-created', 'pages-improved'.
     * @param mixed $value Value of the associated metric.
     * @param int $offset Offset value associated with the metric, such as number of days retention.
     */
    public function __construct(Event $event, string $metric, $value, $offset = null)
    {
        $this->event = $event;
        $this->event->addStatistic($this);
        $this->setMetric($metric);
        $this->value = $value;
        $this->offset = $offset;
    }

    /**
     * Get the Event this EventStat applies to.
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }
}
