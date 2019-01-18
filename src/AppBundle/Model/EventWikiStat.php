<?php
/**
 * This file contains only the EventWikiStat class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use AppBundle\Model\Traits\StatTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * An EventWikiStat is a statistic about a specific event and for a specific wiki.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event_wiki_stat",
 *     indexes={
 *         @ORM\Index(name="ews_event_wiki", columns={"ews_event_wiki_id"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(
 *             name="ews_metrics",
 *             columns={"ews_event_wiki_id", "ews_metric"}
 *         )
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class EventWikiStat
{
    /**
     * Shared methods between EventStat and EventWikiStat models.
     */
    use StatTrait;

    /**
     * Allowed metric types. Keys are the i18n key for the metric, and is what
     * is stored in the database. Values are the applicable wikis for that type
     * of metric, with null meaning all wikis.
     * @see StatTrait Shared methods that use this constant.
     */
    public const METRIC_TYPES = [
        'edits',
        'byte-difference',
        'pages-created',
        'pages-improved',
        'pages-created-pageviews',
        'pages-improved-pageviews',

        // For Commons
        'files-uploaded',
        'file-usage',

        // For Wikidata. These will be the same metrics as for the Event.
        'items-created',
        'items-improved',
    ];

    /**
     * @ORM\Id
     * @ORM\Column(name="ews_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID for the statistic.
     */
    protected $id;

    /**
     * Many EventWikiStats belong to one EventWiki.
     * @ORM\ManyToOne(targetEntity="EventWiki", inversedBy="stats")
     * @ORM\JoinColumn(name="ews_event_wiki_id", referencedColumnName="ew_id", nullable=false)
     * @var EventWiki EventWiki this EventWikiStat applies to.
     */
    protected $wiki;

    /**
     * @ORM\Column(name="ews_metric", type="string", length=32)
     * @var string Name of the event metric, such as 'retention', 'pages-created', 'pages-improved'.
     *   This should correspond with an i18n message.
     */
    protected $metric;

    /**
     * @ORM\Column(name="ews_metric_offset", type="integer", nullable=true)
     * @var int Offset value for the metric, if applicable (e.g. num days retention).
     *   The logic for how this is used lives in ProcessEventCommand.
     */
    protected $offset;

    /**
     * @ORM\Column(name="ews_value", type="integer", options={"default":0})
     * @var int Value of the associated metric.
     */
    protected $value;

    /**
     * EventWikiStat constructor.
     * @param EventWiki $wiki EventWiki the statistic applies to.
     * @param string $metric Name of event metric, e.g. 'retention', 'pages-created', 'pages-improved'.
     * @param mixed $value Value of the associated metric.
     * @param int $offset Offset value associated with the metric, such as number of days retention.
     */
    public function __construct(EventWiki $wiki, string $metric, $value, ?int $offset = null)
    {
        $this->wiki = $wiki;
        $this->wiki->addStatistic($this);
        $this->setMetric($metric);
        $this->value = $value;
        $this->offset = $offset;
    }

    /**
     * Get the Event this EventWikiStat applies to.
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->wiki->getEvent();
    }

    /**
     * Get the EventWiki this EventWikiStat applies to.
     * @return EventWiki
     */
    public function getWiki(): EventWiki
    {
        return $this->wiki;
    }
}
