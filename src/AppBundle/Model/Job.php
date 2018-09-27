<?php
/**
 * This file contains only the Job class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Job is a processing event that has been submitted to the job queue.
 * @ORM\Entity
 * @ORM\Table(
 *     name="job",
 *     indexes={
 *         @ORM\Index(name="job_event", columns={"job_event_id"}),
 *         @ORM\Index(name="job_submitted", columns={"job_submitted_at"}),
 *         @ORM\Index(name="job_started", columns={"job_started"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="job_event_uniq", columns={"job_event_id"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class Job
{
    /**
     * @ORM\Id
     * @ORM\Column(name="job_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID of the job.
     */
    protected $id;

    /**
     * Many Jobs belong to one Event.
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="jobs")
     * @ORM\JoinColumn(name="job_event_id", referencedColumnName="event_id", nullable=false)
     * @var Event Event this Job applies to.
     */
    protected $event;

    /**
     * @ORM\Column(name="job_submitted_at", type="datetime", nullable=false)
     * @var DateTime When the Job was submitted.
     */
    protected $submitted;

    /**
     * @ORM\Column(name="job_started", type="boolean", nullable=false, options={"default": false})
     * @var bool Whether or not the job has been started by the daemon.
     */
    protected $started = false;

    /**
     * Job constructor.
     * @param Event $event Event the Job applies to.
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
        $this->event->addJob($this);
    }

    /**
     * Get the ID of this Job.
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the Event this Job applies to.
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * When the Job was submitted.
     * @return DateTime|null
     */
    public function getSubmitted(): ?DateTime
    {
        return $this->submitted;
    }

    /**
     * Whether or not the Job has been initiated by the daemon.
     * @return bool
     */
    public function getStarted(): bool
    {
        return $this->started;
    }

    /**
     * Flag the Job as having been initiated, or false if specified.
     * @param bool $state
     */
    public function setStarted(bool $state = true): void
    {
        $this->started = $state;
    }

    /**
     * Set the submitted attribute when persisting.
     * @ORM\PrePersist
     */
    public function setSubmitted(): void
    {
        $this->submitted = new DateTime();
    }
}
