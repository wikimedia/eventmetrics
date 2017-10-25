<?php
/**
 * This file contains only the Participant class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * A Participant is a user who participates in an Event.
 * @ORM\Entity
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
class Participant extends Model
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
     * @ORM\Column(name="par_user_id", type="integer")
     * @var int Corresponds to the `gu_id` column in `centralauth`.`globaluser` on the replicas.
     */
    protected $userId;

    /**
     * @ORM\Column(name="par_new_editor", type="boolean")
     * @var bool Whether or not they are considered a new editor, as of the time of the event.
     */
    protected $newEditor;

    /**
     * Event constructor.
     * @param Event $event Event the Participant is participating in.
     * @param int $userId ID of the user, corresponds with `centralauth`.`globaluser`.
     */
    public function __construct(Event $event, $userId)
    {
        $this->event = $event;
        $this->userId = $userId;
    }

    /**
     * Get the Event the Participant is participating in.
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Get the user ID of the Participant.
     */
    public function getUserId()
    {
        return $this->userId;
    }
}
