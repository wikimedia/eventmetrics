<?php
/**
 * This file contains only the EventWiki class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
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
     * A bzcompressed string of all the IDs of pages created. Compression is used so we don't have to store enormous
     * strings. When pulled from the database, this may be of type resource and not string. Use self::getPages() to get
     * an array representation of the page IDs.
     * @see https://secure.php.net/manual/en/function.bzcompress.php
     * @ORM\Column(name="ew_pages_created", type="blob", nullable=true)
     * @var string|resource
     */
    protected $pagesCreated;

    /**
     * A bzcompressed string of all the IDs of pages improved. See note above in $pagesCreated docblock about storage.
     * @ORM\Column(name="ew_pages_edited", type="blob", nullable=true)
     * @var string|resource
     */
    protected $pagesEdited;

    /**
     * A bzcompressed string of all the IDs of the pages for files uploaded.
     * See note above in $pagesCreated docblock about storage.
     * @ORM\Column(name="ew_pages_files", type="blob", nullable=true)
     * @var string|resource
     */
    protected $pagesFiles;

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
        $this->pagesCreated = null;
        $this->pagesEdited = null;
        $this->pagesFiles = null;
    }

    /*********
     * PAGES *
     *********/

    /**
     * Get the cached/persisted page IDs of all pages this event touches (both created and improved).
     * File are handled separately. Use self::getPagesFiles().
     * @return int[]
     */
    public function getPages(): array
    {
        return array_merge($this->getPagesCreated(), $this->getPagesEdited());
    }

    /**
     * Get the cached/persisted page IDs of all pages created during this event.
     * @return int[]
     */
    public function getPagesCreated(): array
    {
        return $this->getPageIds('created');
    }

    /**
     * @param int[]|null $ids
     */
    public function setPagesCreated(?array $ids): void
    {
        $this->setPageIds('created', $ids);
    }

    /**
     * Get the cached/persisted page IDs of all pages edited during this event (they may also have been created).
     * @return int[]
     */
    public function getPagesEdited(): array
    {
        return $this->getPageIds('edited');
    }

    /**
     * @param int[]|null $ids
     */
    public function setPagesEdited(?array $ids): void
    {
        $this->setPageIds('edited', $ids);
    }

    /**
     * Get the cached/persisted page IDs of all pages for files uploaded during this event.
     * @return int[]
     */
    public function getPagesFiles(): array
    {
        return $this->getPageIds('files');
    }

    /**
     * @param int[]|null $ids
     */
    public function setPagesFiles(?array $ids): void
    {
        $this->setPageIds('files', $ids);
    }

    /**
     * @param string $type Which type of page IDs to return.
     * @return int[]
     * @throws Exception With invalid type.
     */
    protected function getPageIds(string $type): array
    {
        if (!in_array($type, ['created', 'edited', 'files'])) {
            throw new Exception('$type must be "created", "edited" or "files".');
        }
        $propertyName = 'pages'.ucfirst($type);
        if (null === $this->$propertyName) {
            return [];
        }

        $blob = is_resource($this->$propertyName)
            ? stream_get_contents($this->$propertyName)
            : $this->$propertyName;

        // Decompressing the empty string or null results in an empty string, so array_filter removes this.
        return array_filter(explode(',', bzdecompress($blob)));
    }

    /**
     * Set the $this->pages property from the IDs, as bzcompressed and base64-encoded string.
     * @see https://secure.php.net/manual/en/function.bzcompress.php
     * @param string $type Which type of page IDs to store: 'created' or 'edited'.
     * @param int[]|null $ids
     * @throws Exception With invalid type.
     */
    protected function setPageIds(string $type, ?array $ids):void
    {
        if (!in_array($type, ['created', 'edited', 'files'])) {
            throw new Exception('$type must be "created", "edited" or "files".');
        }
        $propertyName = 'pages'.ucfirst($type);
        if (null === $ids) {
            $this->$propertyName = null;
            return;
        }
        $filteredIds = array_filter($ids, function ($id) {
            if (!is_numeric($id)) {
                // Will be filtered out.
                return null;
            }
            return (int)$id;
        });
        $this->$propertyName = bzcompress(implode(',', $filteredIds));
    }
}
