<?php
/**
 * This file contains only the Program class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * A Program has its own title, with many organizers and many events.
 * @ORM\Entity
 * @ORM\Table(
 *     name="program",
 *     indexes={@ORM\Index(name="program_title", columns={"program_title"})},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="program_title_uniq", columns={"program_title"})},
 *     options={"engine":"InnoDB"}
 * )
 */
class Program extends Model
{
    /**
     * @ORM\Id
     * @ORM\Column(name="program_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID of the program.
     */
    protected $id;

    /**
     * @ORM\Column(name="program_title", type="string", length=255)
     * @var string The title of the program.
     */
    protected $title;

    /**
     * One Program has many Events.
     * @ORM\OneToMany(targetEntity="Event", mappedBy="program", orphanRemoval=true)
     * @var ArrayCollection|Event[] Events that belong to this program.
     */
    protected $events;

    /**
     * Many Programs have many Organizers.
     * @ORM\ManyToMany(targetEntity="Organizer", inversedBy="programs")
     * @ORM\JoinTable(
     *     name="organizers_programs",
     *     joinColumns={
     *         @ORM\JoinColumn(name="program_id", referencedColumnName="program_id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="org_id", referencedColumnName="org_id")
     *     }
     * )
     * @var ArrayCollection|Organizer[] Organizers of this program.
     */
    protected $organizers;

    /**
     * Program constructor.
     * @param string $title
     */
    public function __construct($title)
    {
        $this->title = trim($title);
        $this->events = new ArrayCollection();
        $this->organizers = new ArrayCollection();
    }

    /**
     * Get the title of this Program.
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get Organizers of this Program.
     * @return ArrayCollection|Organizer[]
     */
    public function getOrganizers()
    {
        return $this->organizers;
    }

    /**
     * Add an organizer to this Program.
     * @param Organizer $organizer
     */
    public function addOrganizer(Organizer $organizer)
    {
        if ($this->organizers->contains($organizer)) {
            return;
        }
        $this->organizers->add($organizer);
        $organizer->addProgram($this);
    }

    /**
     * Remove an organizer from this Program.
     * @param Organizer $organizer
     */
    public function removeOrganizer(Organizer $organizer)
    {
        if (!$this->organizers->contains($organizer)) {
            return;
        }
        $this->organizers->removeElement($organizer);
        $organizer->removeProgram($this);
    }

    /**
     * Get Events belonging to this Program.
     * @return ArrayCollection|Event[]
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Add an event to this Program.
     * @param Event $event
     */
    public function addEvent(Event $event)
    {
        if ($this->events->contains($event)) {
            return;
        }
        $this->events->add($event);
    }

    /**
     * Remove an event from this Program.
     * @param Event $event
     */
    public function removeEvent(Event $event)
    {
        if (!$this->events->contains($event)) {
            return;
        }
        $this->events->removeElement($event);
    }
}
