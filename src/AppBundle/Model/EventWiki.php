<?php
/**
 * This file contains only the EventWiki class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * An EventWiki belongs to an Event.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event_wiki",
 *     indexes={
 *         @ORM\Index(name="ew_event", columns={"ew_event_id"}),
 *         @ORM\Index(name="ew_wiki", columns={"ew_dbname"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ew_event_wiki", columns={"ew_event_id", "ew_dbname"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class EventWiki extends Model
{
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
     * @ORM\Column(name="ew_dbname", type="string", length=32)
     * @var string Database name of the wiki. Corresponds to `dbname` in `meta`.`wiki`.
     */
    protected $dbName;

    /**
     * Event constructor.
     * @param int $eventId Foreign key corresponding to `event`.`event_id`.
     * @param int $string Database name of the wiki. Corresponds to `dbname` in `meta`.`wiki`.
     */
    public function __construct(Event $event, $dbName)
    {
        $this->event = $event;
        $this->event->addWiki($this);
        $this->dbName = $dbName;
    }

    /**
     * Get the Event the Participant is participating in.
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Get the database name.
     */
    public function getDbName()
    {
        return $this->dbName;
    }
}
