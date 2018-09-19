<?php
/**
 * This file contains only the Event class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use AppBundle\Model\Traits\EventStatTrait;
use AppBundle\Model\Traits\TitleUserTrait;

/**
 * An Event belongs to one program, and has many participants.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event",
 *     indexes={
 *         @ORM\Index(name="event_time", columns={"event_start", "event_end"}),
 *         @ORM\Index(name="event_title", columns={"event_title"}),
 *         @ORM\Index(name="event_program", columns={"event_program_id"}),
 *         @ORM\Index(name="event_program_title", columns={"event_program_id", "event_title"}),
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="event_title_program_uniq", columns={"event_program_id", "event_title"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 * @UniqueEntity("title", message="error-event-title-dup")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventRepository")
 */
class Event
{
    /**
     * Available metrics type, hard-coded here for accessibility,
     * while the logic to compute these stats lives in EventProcessor.
     *
     * Keys are i18n message keys, values are the 'offset' values.
     *
     * The order specified here is also the order it will appear in the interface.
     *
     * @see EventProcessor
     * @see EventStat
     */
    const AVAILABLE_METRICS = [
        'new-editors' => 15,
        'retention' => 7,
        'pages-created' => null,
        'pages-improved' => null,
        'files-uploaded' => null,
        'file-usage' => null,
        'items-created' => null,
        'items-improved' => null,
    ];

    /**
     * This defines what metrics are available to what wiki families.
     * '*' means all wikis are applicable.
     */
    const WIKI_FAMILY_METRIC_MAP = [
        '*' => ['new-editors', 'retention'],
        'wikipedia' => ['pages-created', 'pages-improved'],
        'commons' => ['files-uploaded', 'file-usage'],
        'wikidata' => ['items-created', 'items-improved'],
    ];

    /**
     * NOTE: Some methods pertaining to titles and Participants live in the TitleUserTrait trait.
     */
    use TitleUserTrait;

    /**
     * Used purely to move out some of the logic to a dedicated file.
     */
    use EventStatTrait;

    /**
     * @ORM\Id
     * @ORM\Column(name="event_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID of the event.
     */
    protected $id;

    /**
     * Many Events have one Program.
     * @ORM\ManyToOne(targetEntity="Program", inversedBy="events")
     * @ORM\JoinColumn(name="event_program_id", referencedColumnName="program_id", nullable=false)
     * @var Program Program to which this event belongs.
     */
    protected $program;

    /**
     * One Event has many Participants.
     * @ORM\OneToMany(
     *     targetEntity="Participant", mappedBy="event", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY"
     * )
     * @var ArrayCollection|Participant[] Participants of this Event.
     */
    protected $participants;

    /**
     * One Event has many EventStats.
     * @ORM\OneToMany(targetEntity="EventStat", mappedBy="event", orphanRemoval=true)
     * @var ArrayCollection|EventStat[] Statistics of this Event.
     */
    protected $stats;

    /**
     * One Event has many EventWikis.
     * @ORM\OneToMany(targetEntity="EventWiki", mappedBy="event", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|EventWiki[] Wikis that this event takes place on.
     */
    protected $wikis;

    /**
     * One Event has many EventCategories.
     * @ORM\OneToMany(targetEntity="EventCategory", mappedBy="event", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|EventCategory[] Categories that this event takes place on.
     */
    protected $categories;

    /**
     * @ORM\Column(name="event_title", type="string", length=255, unique=true)
     * @Assert\Type("string")
     * @Assert\Length(max=255)
     * @var string The title of the event.
     */
    protected $title;

    /**
     * @ORM\Column(name="event_start", type="datetime", nullable=false)
     * @Assert\DateTime(message="error-invalid", payload={"0"="start-date"})
     * @Assert\NotNull(message="error-invalid", payload={"0"="start-date"})
     * @var DateTime The start date and time of the event.
     */
    protected $start;

    /**
     * @ORM\Column(name="event_end", type="datetime", nullable=false)
     * @Assert\DateTime(message="error-invalid", payload={"0"="end-date"})
     * @Assert\NotNull(message="error-invalid", payload={"0"="end-date"})
     * @var DateTime The end date and time of the event.
     */
    protected $end;

    /**
     * @ORM\Column(
     *     name="event_timezone",
     *     type="string",
     *     length=64,
     *     options={"default":"UTC"}
     * )
     * @var string The end date and time of the the event.
     */
    protected $timezone;

    /**
     * @ORM\Column(name="event_updated_at", type="datetime", nullable=true)
     * @var DateTime The last time statistics were updated for this event.
     */
    protected $updated;

    /**
     * One Event has many Jobs.
     * @ORM\OneToMany(targetEntity="Job", mappedBy="event", orphanRemoval=true)
     * @var ArrayCollection|Job[] Jobs for this Event.
     */
    protected $jobs;

    /**
     * Event constructor.
     * @param Program $program Program that this event belongs to.
     * @param string $title Title of the event. This should be unique for the program.
     * @param DateTime|string $start Start date of the event.
     * @param DateTime|string $end End date of the event.
     * @param string $timezone Official timezone code within the tz database.
     */
    public function __construct(Program $program, $title = null, $start = null, $end = null, $timezone = 'UTC')
    {
        $this->program = $program;
        $this->setTitle($title);
        $this->setTimezone($timezone);
        $this->assignDate('start', $start);
        $this->assignDate('end', $end);

        $this->participants = new ArrayCollection();
        $this->stats = new ArrayCollection();
        $this->wikis = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->jobs = new ArrayCollection();
    }

    /**
     * The class name of users associated with Events.
     * This is referenced in TitleUserTrait.
     * @see TitleUserTrait
     * @return string
     */
    public function getUserClassName(): string
    {
        return 'Participant';
    }

    /**
     * Get the ID of the event.
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get unique cache key for the Event. This is called by Repository::getCacheKey(),
     * used when making expensive queries against the replicas.
     * @return string
     */
    public function getCacheKey(): string
    {
        return (string)$this->id;
    }

    /**
     * Is the Event valid? If false, statistics will not be able to be generated.
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->wikis->count() > 0 &&
            $this->start !== null &&
            $this->end !== null &&
            $this->getStartWithTimezone() < new DateTime() &&
            $this->participants->count() > 0;
    }

    /***********
     * PROGRAM *
     ***********/

    /**
     * Get the Program associated with this Event.
     * @return Program
     */
    public function getProgram(): Program
    {
        return $this->program;
    }

    /*********
     * DATES *
     *********/

    /**
     * Get the start date of this Event.
     * @return DateTime|null
     */
    public function getStart(): ?DateTime
    {
        return $this->start;
    }

    /**
     * Set the start date of this Event.
     * @param DateTime|string|null $value
     */
    public function setStart($value): void
    {
        $this->assignDate('start', $value);
    }

    /**
     * Get the start date adjusted with the Event's timezone.
     * @return DateTime
     */
    public function getStartWithTimezone(): DateTime
    {
        $dateStr = $this->start->format('YmdHis');
        $dt = new DateTime($dateStr, new DateTimeZone($this->timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt;
    }

    /**
     * Get the end date of this Event.
     * @return DateTime|null
     */
    public function getEnd(): ?DateTime
    {
        return $this->end;
    }

    /**
     * Get the end date adjusted with the Event's timezone.
     * @return DateTime
     */
    public function getEndWithTimezone(): DateTime
    {
        $dateStr = $this->end->format('YmdHis');
        $dt = new DateTime($dateStr, new DateTimeZone($this->timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt;
    }

    /**
     * Set the end date of this Event.
     * @param DateTime|string|null $value
     */
    public function setEnd($value): void
    {
        $this->assignDate('end', $value);
    }

    /**
     * Convert the given date argument to a DateTime and save to class property.
     * @param string $key 'start' or 'end'.
     * @param DateTime|string $value
     */
    private function assignDate(string $key, $value): void
    {
        if ($value instanceof DateTime) {
            $this->{$key} = $value;
        } elseif (is_string($value)) {
            $this->{$key} = new DateTime(
                $value,
                new DateTimeZone('UTC')
            );
        } else {
            $this->{$key} = null;
        }
    }

    /**
     * Get the end date of this Event.
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * Get the display variant of the timezone.
     * @return string
     */
    public function getDisplayTimezone(): string
    {
        return str_replace('_', ' ', $this->timezone);
    }

    /**
     * Get the end date of this Event.
     * @param string $timezone Official timezone code within the tz database.
     */
    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**************
     * STATISTICS *
     **************/

    // @see EventStatTrait

    /**************
     * CATEGORIES *
     **************/

    /**
     * Get categories belonging to this Event.
     * @return ArrayCollection of EventCategories.
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /**
     * Get the number of categories belonging to this Event.
     * @return int
     */
    public function getNumCategories(): int
    {
        return $this->categories->count();
    }

    /**
     * Get the titles of categories belonging to this Event that are for the specified wiki.
     * @param EventWiki $wiki
     * @return string[]
     */
    public function getCategoryTitlesForWiki(EventWiki $wiki): array
    {
        return $this->categories->filter(function (EventCategory $category) use ($wiki) {
            // First get EventCategories that are for the given EventWiki (have the same domain).
            return $category->getDomain() === $wiki->getDomain();
        })->map(function (EventCategory $category) {
            // Loop through again to get their titles.
            return $category->getTitle();
        })->toArray();
    }

    /**
     * Add an EventCategory to the Event.
     * @param EventCategory $category
     */
    public function addCategory(EventCategory $category): void
    {
        if ($this->categories->contains($category)) {
            return;
        }
        $this->categories->add($category);
    }

    /**
     * Remove an EventCategory from the Event.
     * @param EventCategory $category
     */
    public function removeCategory(EventCategory $category): void
    {
        if (!$this->categories->contains($category)) {
            return;
        }
        $this->categories->removeElement($category);
    }

    /**
     * Remove all categories.
     */
    public function clearCategories()
    {
        $this->categories->clear();
    }

    /****************
     * PARTICIPANTS *
     ****************/

    /**
     * Get participants of this Event.
     * @return ArrayCollection of Participants.
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /**
     * Get the number of participants of this Event.
     * @return int
     */
    public function getNumParticipants(): int
    {
        return $this->participants->count();
    }

    /**
     * Add an Participant to this Event.
     * @param Participant $participant
     */
    public function addParticipant(Participant $participant): void
    {
        if ($this->participants->contains($participant)) {
            return;
        }
        $this->participants->add($participant);
    }

    /**
     * Remove a Participant from this Event.
     * @param Participant $participant
     */
    public function removeParticipant(Participant $participant): void
    {
        if (!$this->participants->contains($participant)) {
            return;
        }
        $this->participants->removeElement($participant);
    }

    /**
     * Get the user IDs of all the Participants of this Event.
     * @return int[]
     */
    public function getParticipantIds(): array
    {
        return $this->participants->map(function (Participant $participant) {
            return $participant->getUserId();
        })->toArray();
    }

    /**
     * Get the usernames of the Participants of this Event.
     * @return string[]
     */
    public function getParticipantNames(): array
    {
        return $this->participants->map(function (Participant $participant) {
            return $participant->getUsername();
        })->toArray();
    }

    /**
     * Remove all Participants.
     */
    public function clearParticipants(): void
    {
        $this->participants->clear();
    }

    /********
     * WIKI *
     ********/

    /**
     * Get wikis this event is taking place on.
     * @return ArrayCollection|EventWiki[]
     */
    public function getWikis(): Collection
    {
        return $this->wikis;
    }

    /**
     * Get the EventWiki with the given domain that belongs to this Event.
     * @param string $domain
     * @return EventWiki
     */
    public function getWikiByDomain(string $domain): EventWiki
    {
        return $this->wikis->filter(function (EventWiki $wiki) use ($domain) {
            return $wiki->getDomain() === $domain;
        })->first();
    }

    /**
     * Add an EventWiki to this Event.
     * @param EventWiki $wiki
     */
    public function addWiki(EventWiki $wiki): void
    {
        if ($this->wikis->contains($wiki)) {
            return;
        }
        $this->wikis->add($wiki);
    }

    /**
     * Remove an EventWiki from this Event.
     * @param EventWiki $wiki
     */
    public function removeWiki(EventWiki $wiki): void
    {
        if (!$this->wikis->contains($wiki)) {
            return;
        }
        $this->wikis->removeElement($wiki);
    }

    /***************
     * WIKI FAMILY *
     ***************/

    /**
     * Get all EventWikis belonging to the Event that represent
     * a wiki family (*.wikipedia, *.wiktionary, etc).
     * @return ArrayCollection of EventWikis
     */
    public function getFamilyWikis(): Collection
    {
        return $this->wikis->filter(function (EventWiki $wiki) {
            return substr((string)$wiki->getDomain(), 0, 2) === '*.';
        });
    }

    /**
     * This method returns all EventWikis associated with the Event, grouped by the name of the associated family.
     * It is used for display purposes on the Event page. This does not pay mind to whether there is an EventWiki
     * representing a family (e.g. *.wikipedia). For instance, if there are EventWikis for en.wikipedia, fr.wikipedia,
     * and commons.wikipedia, the two Wikipedias are grouped together. If there's also a *.wikipedia,
     * it is not included in the 'wikipedia' group.
     * @return array
     */
    public function getWikisByFamily(): array
    {
        $wikisByFamily = [];

        foreach ($this->wikis->getIterator() as $wiki) {
            if ($wiki->isFamilyWiki()) {
                continue;
            }

            $familyName = $wiki->getFamilyName();
            if (!isset($wikisByFamily[$familyName])) {
                $wikisByFamily[$familyName] = [$wiki];
            } else {
                $wikisByFamily[$familyName][] = $wiki;
            }
        }

        return $wikisByFamily;
    }

    /**
     * Get all associated EventWikis that belong to a family.
     * @return ArrayCollection of EventWikis
     */
    public function getChildWikis(): Collection
    {
        return $this->wikis->filter(function (EventWiki $wiki) {
            return $wiki->isChildWiki();
        });
    }

    /**
     * Get all EventWikis that are not part of a family that have been added
     * to the Event. For instance, if there is an EventWiki for *.wikipedia
     * (wikipedia family), a fr.wikipedia EventWiki is not returned, but it
     * will if there is not a *.wikipedia EventWiki.
     * @return ArrayCollection of EventWikis
     */
    public function getOrphanWikis(): Collection
    {
        $familyNames = $this->getFamilyWikis()->map(function (EventWiki $eventWiki) {
            return $eventWiki->getFamilyName();
        });

        return $this->wikis->filter(function (EventWiki $wiki) use ($familyNames) {
            return null === $wiki->getDomain()
                || !$familyNames->contains($wiki->getFamilyName());
        });
    }

    /**
     * Remove all associated EventWikis that belong to a family.
     */
    public function clearChildWikis(): void
    {
        $children = $this->getChildWikis()->toArray();
        foreach ($children as $child) {
            $this->removeWiki($child);
        }
    }

    /**
     * Get EventWikis that are represent a wiki family, or an individual wiki that is not part of a family.
     * @return ArrayCollection Containing EventWikis
     */
    public function getOrphanWikisAndFamilies(): ArrayCollection
    {
        return new ArrayCollection(array_merge(
            $this->getFamilyWikis()->toArray(),
            $this->getOrphanWikis()->toArray()
        ));
    }

    /********
     * JOBS *
     ********/

    /**
     * Add a Job for this Event.
     * @param Job $job
     */
    public function addJob(Job $job): void
    {
        if ($this->jobs->contains($job)) {
            return;
        }
        $this->jobs->add($job);
    }

    /**
     * Get jobs associated with this Event (in theory there should be only one).
     * @return Collection of Jobs.
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    /**
     * Get the number of jobs associated with this Event. (Ideally there'd only be one, but this is here just in case.)
     * @return int
     */
    public function getNumJobs(): int
    {
        return $this->jobs->count();
    }

    /**
     * Is there a job associated with this Event?
     * @return boolean
     */
    public function hasJob(): bool
    {
        return $this->getNumJobs() > 0;
    }

    /**
     * Remove all Jobs from this Event.
     */
    public function removeJobs(): void
    {
        $this->jobs->clear();
    }
}
