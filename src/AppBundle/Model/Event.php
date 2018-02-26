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
     * NOTE: Some methods pertaining to titles and Participants
     * live in the TitleUserTrait trait.
     */
    use TitleUserTrait;

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
     * @ORM\OneToMany(targetEntity="Participant", mappedBy="event", orphanRemoval=true, cascade={"persist"})
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
     * Get the ID of the event;
     * @return int
     */
    public function getId()
    {
        return $this->id;
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

    /**
     * Get statistics about this Event.
     * @return ArrayCollection|EventStat[]
     */
    public function getStatistics()
    {
        return $this->stats;
    }

    /**
     * Get the statistic about this Event with the given metric.
     * @param string $metric Name of metric, one of EventStat::METRIC_TYPES.
     * @return EventStat|null Null if no EventStat with given metric was found.
     */
    public function getStatistic($metric)
    {
        $ewStats = $this->stats->filter(function ($stat) use ($metric) {
            return $stat->getMetric() === $metric;
        });
        return $ewStats->count() > 0 ? $ewStats->first() : null;
    }

    /**
     * Add an EventStat to this Event.
     * @param EventStat $eventStat
     */
    public function addStatistic(EventStat $eventStat)
    {
        if ($this->stats->contains($eventStat)) {
            return;
        }
        $this->stats->add($eventStat);
    }

    /**
     * Remove an eventStat from this Event.
     * @param EventStat $eventStat
     */
    public function removeStatistic(EventStat $eventStat)
    {
        if (!$this->stats->contains($eventStat)) {
            return;
        }
        $this->stats->removeElement($eventStat);
    }

    /**
     * Get the date of the last time the EventStat's were refreshed.
     * @return DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Get the update at value adjusted with the Event's timezone.
     * @return DateTime
     */
    public function getUpdatedWithTimezone()
    {
        $this->updated->setTimezone(new DateTimeZone($this->timezone));
        return new DateTime(
            $this->updated->format('Y-m-d H:i:s'),
            new DateTimeZone('UTC')
        );
    }

    /**
     * Set the 'update' attribute, to be set after EventStats
     * have been refreshed.
     * @param DateTime $datestamp
     */
    public function setUpdated($datestamp)
    {
        $this->updated = $datestamp;
    }

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
     * Add a EventWiki to this Event.
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
     * Remove a EventWiki from this Event.
     * @param EventWiki $wiki
     */
    public function removeWiki(EventWiki $wiki)
    {
        if (!$this->wikis->contains($wiki)) {
            return;
        }
        $this->wikis->removeElement($wiki);
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
