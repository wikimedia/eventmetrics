<?php
/**
 * This file contains only the Participant class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A Participant is a user who participates in an Event.
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ParticipantRepository")
 * @ORM\Table(
 *     name="participant",
 *     indexes={
 *         @ORM\Index(name="par_event", columns={"par_event_id"}),
 *         @ORM\Index(name="par_user", columns={"par_user_id"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="par_event_user", columns={"par_event_id", "par_user_id"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class Participant
{
    /**
     * @ORM\Id
     * @ORM\Column(name="par_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Primary key.
     */
    protected $id;

    /**
     * Many Participants belong to one Event.
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="participants")
     * @ORM\JoinColumn(name="par_event_id", referencedColumnName="event_id", nullable=false)
     * @var Event Event this Participant is participating in.
     */
    protected $event;

    /**
     * NotBlank assertion is also handled with validations for Program and Event,
     * so we don't want to render an error message, hence the `message=""`.
     * @ORM\Column(name="par_user_id", type="integer")
     * @Assert\NotBlank(message="")
     * @var int Corresponds to the `gu_id` column in `centralauth`.`globaluser` on the replicas.
     */
    protected $userId;

    /**
     * @var string Username retrieved using the $userId.
     */
    protected $username;

    /**
     * @ORM\Column(name="par_new_editor", type="boolean")
     * @var bool Whether or not they are considered a new editor, as of the time of the event.
     */
    protected $newEditor = false;

    /**
     * Event constructor.
     * @param Event $event Event the Participant is participating in.
     * @param int $userId ID of the user, corresponds with `centralauth`.`globaluser`.
     */
    public function __construct(Event $event, int $userId = null)
    {
        $this->event = $event;
        $this->event->addParticipant($this);
        $this->userId = $userId;
    }

    /**
     * Get the Event the Participant is participating in.
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * Get the user ID of the Participant.
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Set the user ID.
     * Corresponds with `gu_id` on `centralauth`.`globaluser`.
     * @param int $userId
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get the username.
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Set the username.
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }
}
