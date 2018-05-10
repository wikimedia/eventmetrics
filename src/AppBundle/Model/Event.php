<?php
/**
 * This file contains only the Event class.
 */

namespace AppBundle\Model;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;
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
    ];

    /**
     * This defines what metrics are available to what wiki families.
     * '*' means all wikis are applicable.
     */
    const WIKI_FAMILY_METRIC_MAP = [
        '*' => ['new-editors', 'retention'],
        'wikipedia' => ['pages-created', 'pages-improved'],
        'commons' => ['files-uploaded', 'file-usage'],
    ];

    /**
     * NOTE: Some methods pertaining to titles and Participants
     * live in the TitleUserTrait trait.
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
     * @ORM\Column(name="event_valid", type="boolean", options={"default": true})
     * @var bool Whether the event has passed validity checks.
     */
    protected $valid = true;

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
        $this->jobs = new ArrayCollection();
    }

    /**
     * The class name of users associated with Events.
     * This is referenced in TitleUserTrait.
     * @see TitleUserTrait
     * @return string
     */
    public function getUserClassName()
    {
        return 'Participant';
    }

    /**
     * Get the ID of the event.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get unique cache key for the Event. This is called by Repository::getCacheKey(),
     * used when making expensive queries againt the replicas.
     * @return string
     */
    public function getCacheKey()
    {
        return (string)$this->id;
    }

    /***********
     * PROGRAM *
     ***********/

    /**
     * Get the Program associated with this Event.
     * @return Program
     */
    public function getProgram()
    {
        return $this->program;
    }

    /*********
     * DATES *
     *********/

    /**
     * Get the start date of this Event.
     * @return DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set the start date of this Event.
     * @param DateTime|string|null $value
     */
    public function setStart($value)
    {
        $this->assignDate('start', $value);
    }

    /**
     * Get the start date adjusted with the Event's timezone.
     * @return DateTime
     */
    public function getStartWithTimezone()
    {
        $dateStr = $this->start->format('YmdHis');
        $dt = new DateTime($dateStr, new DateTimeZone($this->timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt;
    }

    /**
     * Get the end date of this Event.
     * @return DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Get the end date adjusted with the Event's timezone.
     * @return DateTime
     */
    public function getEndWithTimezone()
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
    public function setEnd($value)
    {
        $this->assignDate('end', $value);
    }

    /**
     * Convert the given date argument to a DateTime and save to class property.
     * @param  string $key 'start' or 'end'.
     * @param  DateTime|string $value
     */
    private function assignDate($key, $value)
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
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Get the display variant of the timezone.
     * @return string
     */
    public function getDisplayTimezone()
    {
        return str_replace('_', ' ', $this->timezone);
    }

    /**
     * Get the end date of this Event.
     * @param string $timezone Official timezone code within the tz database.
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    /**************
     * STATISTICS *
     **************/

    // @see EventStatTrait

    /****************
     * PARTICIPANTS *
     ****************/

    /**
     * Get participants of this Event.
     * @return ArrayCollection|Participant[]
     */
    public function getParticipants()
    {
        return $this->participants;
    }

    /**
     * Get the number of participants of this Event.
     * @return int
     */
    public function getNumParticipants()
    {
        return count($this->participants);
    }

    /**
     * Add an Participant to this Event.
     * @param Participant $participant
     */
    public function addParticipant(Participant $participant)
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
    public function removeParticipant(Participant $participant)
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
    public function getParticipantIds()
    {
        return array_map(function ($participant) {
            return $participant->getUserId();
        }, $this->participants->toArray());
    }

    /**
     * Get the usernames of the Participants of this Event.
     * @return string[]
     */
    public function getParticipantNames()
    {
        return array_map(function ($participant) {
            return $participant->getUsername();
        }, $this->participants->toArray());
    }

    /**
     * Remove all Participants.
     */
    public function clearParticipants()
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
    public function getWikis()
    {
        return $this->wikis;
    }

    /**
     * Add an EventWiki to this Event.
     * @param EventWiki $wiki
     */
    public function addWiki(EventWiki $wiki)
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
    public function removeWiki(EventWiki $wiki)
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
     * @return EventWiki[]
     */
    public function getFamilyWikis()
    {
        return $this->wikis->filter(function ($wiki) {
            return substr($wiki->getDomain(), 0, 2) === '*.';
        });
    }

    /**
     * Get all associated EventWikis that belong to a family.
     * @return EventWiki[]
     */
    public function getChildWikis()
    {
        return $this->wikis->filter(function ($wiki) {
            return $wiki->isChildWiki();
        });
    }

    /**
     * Remove all associated EventWikis that belong to a family.
     */
    public function clearChildWikis()
    {
        $children = $this->getChildWikis()->toArray();
        foreach ($children as $child) {
            $this->removeWiki($child);
        }
    }

    /**
     * Get all EventWikis that are not part of a family that has been added
     * to the Event. For instance, if there is an EventWiki for *.wikipedia
     * (wikipedia family), a fr.wikipedia EventWiki is not returned, but it
     * will if there is not a *.wikipedia EventWiki/
     * @return EventWiki[]
     */
    public function getOrphanWikis()
    {
        $familyNames = $this->getFamilyWikis()->map(function ($eventWiki) {
            return $eventWiki->getFamilyName();
        });

        return $this->wikis->filter(function ($wiki) use ($familyNames) {
            return null === $wiki->getDomain()
                || !$familyNames->contains(explode('.', $wiki->getDomain())[1]);
        });
    }

    /**
     * Get EventWikis that are represent a wiki family, or an individual wiki
     * that is not part of a family.
     * @return EventWiki
     */
    public function getOrphanWikisAndFamilies()
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
    public function addJob(Job $job)
    {
        if ($this->jobs->contains($job)) {
            return;
        }
        $this->jobs->add($job);
    }

    /**
     * Get jobs associated with this Event (in theory there should be only one).
     * @return Job[]
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Get the number of jobs associated with this Event.
     * (Ideally there'd only be one, but this is here just in case.)
     * @return int
     */
    public function getNumJobs()
    {
        return count($this->jobs);
    }

    /**
     * Is there a job associated with this Event?
     * @return boolean
     */
    public function hasJob()
    {
        return $this->getNumJobs() > 0;
    }

    /**
     * Remove all Jobs from this Event.
     */
    public function removeJobs()
    {
        $this->jobs->clear();
    }
}
