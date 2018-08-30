<?php
/**
 * This file contains only the EventWiki class.
 */

namespace AppBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An EventWiki belongs to an Event, and also connects an EventStat
 * to a specific wiki and event.
 * @ORM\Entity
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
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventWikiRepository")
 */
class EventWiki
{
    /**
     * Regex pattern of the supported wikis.
     */
    const VALID_WIKI_PATTERN = '/\w+\.wikipedia|commons\.wikimedia|www\.wikidata/';

    /**
     * Valid names of wiki families, or singular orphan wikis like commons.
     */
    const FAMILY_NAMES = [
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
     * The database name of the wiki. This is not persisted but can be stored here for convenience when
     * passing around EventWikis and running queries on them.
     * @var string
     */
    protected $dbName;

    /**
     * One EventWiki has many EventStats.
     * @ORM\OneToMany(targetEntity="EventWikiStat", mappedBy="wiki", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|EventStat[] Statistics for this EventWiki.
     */
    protected $stats;

    /**
     * One EventWiki has many EventCategory's.
     * @ORM\OneToMany(targetEntity="EventCategory", mappedBy="wiki", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|EventCategory[] Categories for this EventWiki.
     */
    protected $categories;

    /**
     * EventWiki constructor.
     * @param Event $event Event that this EventWiki belongs to.
     * @param string $domain Domain name of the wiki, without the .org.
     */
    public function __construct(Event $event, $domain = null)
    {
        $this->event = $event;
        $this->event->addWiki($this);
        $this->domain = $domain;
        $this->stats = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    /**
     * Get the Event this EventWiki belongs to.
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Get the domain name.
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Get the database name of the wiki, if it has been provided.
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * Set the database name of the wiki.
     * @param $dbName
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;
    }

    /**
     * Get the regex pattern for valid wikis.
     * @return string
     * @static
     *
     * No need to test a hard-coded string.
     * @codeCoverageIgnore
     */
    public static function getValidPattern()
    {
        return self::VALID_WIKI_PATTERN;
    }

    /**************
     * CATEGORIES *
     **************/

    /**
     * Get categories configured for this wiki.
     * @return EventCategory[]|ArrayCollection
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Get the IDs of all the categories for this EventWiki.
     * This correlates to the cat_id in the category table on the WMF replicas.
     * @return array
     */
    public function getCategoryIds()
    {
        return array_map(function (EventCategory $category) {
            return $category->getCategoryId();
        }, $this->categories->toArray());
    }

    /**
     * Add an EventCategory to this EventWiki.
     * @param EventCategory $category
     */
    public function addCategory(EventCategory $category)
    {
        if ($this->categories->contains($category)) {
            return;
        }
        $this->categories->add($category);
    }

    /**
     * Remove an EventCategory from this EventWiki.
     * @param EventCategory $category
     */
    public function removeCategory(EventCategory $category)
    {
        if (!$this->categories->contains($category)) {
            return;
        }
        $this->categories->removeElement($category);
    }

    /**
     * Clear all associated statistics.
     */
    public function clearCategories()
    {
        $this->categories->clear();
    }

    /***************
     * WIKI FAMILY *
     ***************/

    /**
     * Does this EventWiki represent a wiki family? e.g. *.wikipedia, *.wiktionary
     * @return bool
     */
    public function isFamilyWiki()
    {
        return substr($this->domain, 0, 2) === '*.';
    }

    /**
     * Get the family name of this wiki ('wikipedia', 'commons', etc.).
     * @return string|null
     */
    public function getFamilyName()
    {
        foreach (self::FAMILY_NAMES as $family) {
            if (strpos($this->domain, $family) !== false) {
                return $family;
            }
        }

        // Shouldn't happen...
        return null;
    }

    /**
     * If this EventWiki represents a family, return all EventWikis of the Event that belong to the family.
     * @return ArrayCollection of EventWikis
     */
    public function getChildWikis()
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
    public function isChildWiki()
    {
        return !$this->isFamilyWiki() && !$this->event->getOrphanWikis()->contains($this);
    }

    /**************
     * STATISTICS *
     **************/

    /**
     * Get statistics about this EventWiki.
     * @return ArrayCollection|EventWikiStat[]
     */
    public function getStatistics()
    {
        return $this->stats;
    }

    /**
     * Get the statistic about this EventWiki with the given metric.
     * @param string $metric Name of metric, one of EventWikiStat::METRIC_TYPES.
     * @return EventWikiStat|null Null if no EventWikiStat with given metric was found.
     */
    public function getStatistic($metric)
    {
        $ewStats = array_filter($this->stats->toArray(), function (EventWikiStat $stat) use ($metric) {
            return $stat->getMetric() === $metric;
        });
        return count($ewStats) > 0 ? reset($ewStats) : null;
    }

    /**
     * Add an EventWikiStat to this EventWiki.
     * @param EventWikiStat $eventWikiStat
     */
    public function addStatistic(EventWikiStat $eventWikiStat)
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
    public function removeStatistic(EventWikiStat $eventWikiStat)
    {
        if (!$this->stats->contains($eventWikiStat)) {
            return;
        }
        $this->stats->removeElement($eventWikiStat);
    }

    /**
     * Clear all associated statistics.
     */
    public function clearStatistics()
    {
        $this->stats->clear();
    }
}
