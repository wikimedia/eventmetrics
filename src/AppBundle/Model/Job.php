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
 *         @ORM\Index(name="job_status", columns={"job_status"})
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
    // Constants representing the status of the Job. The Job gets deleted when complete, hence we have no such value.
    public const STATUS_QUEUED = 0;
    public const STATUS_STARTED = 1;
    public const STATUS_FAILED_TIMEOUT = 2;
    public const STATUS_FAILED_UNKNOWN = 3;

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
     * @ORM\Column(name="job_status", type="smallint", nullable=false, options={"default": 0})
     * @var int Status of the job. See constants at the top of this class.
     */
    protected $status = self::STATUS_QUEUED;

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
     * Get the status of the Job. Correlates to one of the self::STATUS_ constants.
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Set the status of the Job. Must be one of the self::STATUS_ constants.
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * Whether or not the Job is currently running.
     * @return bool
     */
    public function hasStarted(): bool
    {
        return self::STATUS_STARTED === $this->status;
    }

    /**
     * Is the job in a failed state?
     * @return bool
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED_UNKNOWN, self::STATUS_FAILED_TIMEOUT]);
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
     * Set the submitted attribute when persisting.
     * @ORM\PrePersist
     */
    public function setSubmitted(): void
    {
        $this->submitted = new DateTime();
    }
}
