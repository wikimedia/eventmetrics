<?php
/**
 * This file contains only the EventWiki class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An EventWiki belongs to an Event, and also connects an EventStat to a specific wiki and event.
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventWikiRepository")
 * @ORM\Table(
 *     name="event_wiki",
 *     indexes={
 *         @ORM\Index(name="ew_event", columns={"ew_event_id"}),
 *         @ORM\Index(name="ew_domain", columns={"ew_domain"}),
 *         @ORM\Index(name="ew_event_domain", columns={"ew_event_id", "ew_domain"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ew_event_wiki", columns={"ew_event_id", "ew_domain"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class EventWiki
{
    /**
     * Regex pattern of the supported wikis.
     */
    public const VALID_WIKI_PATTERN = '/\w+\.wikipedia|commons\.wikimedia|www\.wikidata/';

    /**
     * Valid names of wiki families, or singular orphan wikis like commons.
     */
    public const FAMILY_NAMES = [
        'wikipedia',
        'commons',
        'wikidata',
    ];

    /**
     * @ORM\Id
     * @ORM\Column(name="ew_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID of the event.
     */
    protected $id;

    /**
     * Many EventWikis belong to one Event.
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="wikis")
     * @ORM\JoinColumn(name="ew_event_id", referencedColumnName="event_id", nullable=false)
     * @var Event Event this EventWiki applies to.
     */
    protected $event;

    /**
     * @ORM\Column(name="ew_domain", type="string", length=255, nullable=false)
     * @Assert\NotBlank(message="")
     * @var string Domain of the wiki, without the .org.
     */
    protected $domain;

    /**
     * One EventWiki has many EventStats.
     * @ORM\OneToMany(targetEntity="EventWikiStat", mappedBy="wiki", orphanRemoval=true, cascade={"persist"})
     * @var Collection|EventStat[] Statistics for this EventWiki.
     */
    protected $stats;

    /**
     * A bzcompressed string of all the page IDs. Compression is used so we don't have to store enormous strings.
     * When pulled from the database, this may be of type resource and not string. Use self::getPages() to get an
     * array representation of the page IDs.
     * @see https://secure.php.net/manual/en/function.bzcompress.php
     * @ORM\Column(name="ew_pages", type="blob", length=512, nullable=true)
     * @var string|resource
     */
    protected $pages;

    /**
     * EventWiki constructor.
     * @param Event $event Event that this EventWiki belongs to.
     * @param string $domain Domain name of the wiki, without the .org.
     */
    public function __construct(Event $event, ?string $domain = null)
    {
        $this->event = $event;
        $this->event->addWiki($this);
        $this->domain = $domain;
        $this->stats = new ArrayCollection();
    }

    /**
     * Get the Event this EventWiki belongs to.
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * Get the domain name.
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Get the regex pattern for valid wikis.
     * @return string
     * @static
     *
     * No need to test a hard-coded string.
     * @codeCoverageIgnore
     */
    public static function getValidPattern(): string
    {
        return self::VALID_WIKI_PATTERN;
    }

    /***************
     * WIKI FAMILY *
     ***************/

    /**
     * Does this EventWiki represent a wiki family? e.g. *.wikipedia, *.wiktionary
     * @return bool
     */
    public function isFamilyWiki(): bool
    {
        return '*.' === substr($this->domain, 0, 2);
    }

    /**
     * Get the family name of this wiki ('wikipedia', 'commons', etc.).
     * @return string|null
     */
    public function getFamilyName(): ?string
    {
        foreach (self::FAMILY_NAMES as $family) {
            if (false !== strpos($this->domain, $family)) {
                return $family;
            }
        }

        // Shouldn't happen...
        return null;
    }

    /**
     * If this EventWiki represents a family, return all EventWikis of the Event that belong to the family.
     * @return Collection|EventWiki[]
     */
    public function getChildWikis(): Collection
    {
        if (!$this->isFamilyWiki()) {
            return new ArrayCollection([]);
        }

        $family = $this->getFamilyName();

        return $this->event->getWikis()->filter(function (EventWiki $wiki) use ($family) {
            return $wiki->isChildWiki() &&
                $wiki->getFamilyName() === $family &&
                $wiki->getDomain() !== $this->domain;
        });
    }

    /**
     * Is this EventWiki a child of a family EventWiki that belongs to the same Event?
     * @return bool
     */
    public function isChildWiki(): bool
    {
        return !$this->isFamilyWiki() && !$this->event->getOrphanWikis()->contains($this);
    }

    /**************
     * STATISTICS *
     **************/

    /**
     * Get statistics about this EventWiki.
     * @return Collection|EventWikiStat[]
     */
    public function getStatistics(): Collection
    {
        return $this->stats;
    }

    /**
     * Get the statistic about this EventWiki with the given metric.
     * @param string $metric Name of metric, one of EventWikiStat::METRIC_TYPES.
     * @return EventWikiStat|null Null if no EventWikiStat with given metric was found.
     */
    public function getStatistic(string $metric): ?EventWikiStat
    {
        $ewStats = $this->stats->filter(function (EventWikiStat $stat) use ($metric) {
            return $stat->getMetric() === $metric;
        });
        return $ewStats->count() > 0 ? $ewStats->first() : null;
    }

    /**
     * Add an EventWikiStat to this EventWiki.
     * @param EventWikiStat $eventWikiStat
     */
    public function addStatistic(EventWikiStat $eventWikiStat): void
    {
        if ($this->stats->contains($eventWikiStat)) {
            return;
        }
        $this->stats->add($eventWikiStat);
    }

    /**
     * Remove an EventWikiStat from this EventWiki.
     * @param EventWikiStat $eventWikiStat
     */
    public function removeStatistic(EventWikiStat $eventWikiStat): void
    {
        if (!$this->stats->contains($eventWikiStat)) {
            return;
        }
        $this->stats->removeElement($eventWikiStat);
    }

    /**
     * Clear all associated statistics.
     */
    public function clearStatistics(): void
    {
        $this->stats->clear();

        // It's safe to assume page IDs should also be cleared.
        $this->pages = null;
    }

    /*********
     * PAGES *
     *********/

    /**
     * Get the cached/persisted page IDs of all pages this event touches.
     * @return int[]
     */
    public function getPages(): array
    {
        if (null === $this->pages) {
            return [];
        }

        $blob = is_resource($this->pages)
            ? stream_get_contents($this->pages)
            : $this->pages;

        return explode(',', bzdecompress($blob));
    }

    /**
     * Set the $this->pages property from the IDs, as bzcompressed and base64-encoded string.
     * @see https://secure.php.net/manual/en/function.bzcompress.php
     * @param int[]|null $ids
     */
    public function setPages(?array $ids): void
    {
        if (null === $ids) {
            $this->pages = null;
            return;
        }

        $this->pages = bzcompress(implode(',', $ids));
    }
}
