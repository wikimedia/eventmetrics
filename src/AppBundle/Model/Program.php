<?php
/**
 * This file contains only the Program class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\Model\Organizer;
use AppBundle\Repository\ProgramRepository;
use AppBundle\Repository\OrganizerRepository;

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
     * @Assert\Type("string")
     * @Assert\Length(max = 255)
     * @fixme i18n for maxMessage (currently using default)
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
     * @ORM\ManyToMany(targetEntity="Organizer", inversedBy="programs", orphanRemoval=true, cascade={"persist"})
     * @ORM\JoinTable(
     *     name="organizers_programs",
     *     joinColumns={
     *         @ORM\JoinColumn(name="program_id", referencedColumnName="program_id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="org_id", referencedColumnName="org_id")
     *     }
     * )
     * @Assert\Count(min = 1)
     * @var ArrayCollection|Organizer[] Organizers of this program.
     */
    protected $organizers;

    /**
     * Program constructor.
     * @param Organizer $organizer Original organizer of the program.
     * @param Container|null $container The DI container.
     */
    public function __construct(Organizer $organizer, $container = null)
    {
        $this->events = new ArrayCollection();
        $this->organizers = new ArrayCollection();

        // Set the repository and its container.
        if ($container) {
            $repo = new ProgramRepository();
            $repo->setContainer($container);
            $this->setRepository($repo);
        }

        // Add initial organizer.
        $this->addOrganizer($organizer);
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
     * Get the slug of the Program to be used in URLs.
     * @return string
     */
    public function getSlug()
    {
        // Strip everything but unicode letters and digits, and convert spaces to underscores.
        $sanitized = preg_replace('/[^\p{L}0-9 ]|#|\?/', '', $this->title);
        return str_replace(' ', '_', trim($sanitized));
    }

    /**
     * Set the title of this Program.
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
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
     * Get the number of Organizers of this Program.
     * @return int
     */
    public function getNumOrganizers()
    {
        return count($this->organizers);
    }

    /**
     * Get the user IDs of all the organizers of this program.
     * @return int[]
     */
    public function getOrganizerIds()
    {
        return array_map(function ($organizer) {
            return $organizer->getUserId();
        }, $this->organizers->toArray());
    }

    /**
     * Get the usernames of the Organizers of this Program.
     * @return ArrayCollection|string[]
     */
    public function getOrganizerNames()
    {
        // FIXME: get in one go instead of individually.
        return array_map(function ($organizer) {
            $this->setRepositoryOnOrganizer($organizer);
            return $organizer->getUsername();
        }, $this->organizers->toArray());
    }

    /**
     * Set the Organizers of this Program with the given usernames.
     * @param array $usernames Usernames of the organizers.
     */
    public function setOrganizerNames(array $usernames)
    {
        // Clear out existing organizers.
        $this->organizers->clear();

        // Instantiate a new organizer for each username and add to the program.
        foreach ($usernames as $username) {
            $organizer = new Organizer($username);
            $this->addOrganizer($organizer);
        }
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
        $this->setRepositoryOnOrganizer($organizer);
        $this->organizers->add($organizer);
        $organizer->addProgram($this);
    }

    /**
     * Assign an OrganizerRepository to the given Organizer, and set the container.
     * @todo This doesn't feel right. Perhaps there's a cleaner way?
     * @param Organizer $organizer [description]
     */
    private function setRepositoryOnOrganizer(Organizer $organizer)
    {
        $container = $this->getRepository()->getContainer();
        if (isset($container) && !$organizer->hasRepository()) {
            $organizerRepo = new OrganizerRepository();
            $organizerRepo->setContainer($container);
            $organizer->setRepository($organizerRepo);
        }
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
     * Get the number of Events belonging to this Program.
     * @return int
     */
    public function getNumEvents()
    {
        return count($this->events);
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
