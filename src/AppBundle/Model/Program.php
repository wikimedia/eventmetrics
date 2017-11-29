<?php
/**
 * This file contains only the Program class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
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
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProgramRepository")
 * @UniqueEntity("title", message="error-program-title-dup")
 */
class Program
{
    /**
     * @ORM\Id
     * @ORM\Column(name="program_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID of the program.
     */
    protected $id;

    /**
     * @ORM\Column(name="program_title", type="string", length=255, unique=true)
     * @Assert\Type("string")
     * @Assert\Length(max=255)
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
    public function __construct(Organizer $organizer)
    {
        $this->events = new ArrayCollection();
        $this->organizers = new ArrayCollection();

        // Add initial organizer.
        $this->addOrganizer($organizer);
    }

    /**
     * Get the ID of the program.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /*********
     * TITLE *
     *********/

    /**
     * Get the title of this Program.
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the title of this Program.
     * @param string $title
     */
    public function setTitle($title)
    {
        // Enforce unicode, and use underscores instead of spaces.
        $this->title = str_replace(' ', '_', utf8_encode(trim($title)));
    }

    /**
     * Get the display variant of the program title.
     * @param string $title
     */
    public function getDisplayTitle()
    {
        return str_replace('_', ' ', $this->title);
    }

    /**
     * @Assert\Callback
     * @param ExecutionContext $context Supplied by Symfony.
     */
    public function validateUnreservedTitle(ExecutionContext $context)
    {
        if (in_array($this->title, ['edit', 'delete'])) {
            $context->buildViolation('error-program-title-reserved')
                ->setParameter(0, '<code>edit</code>, <code>delete</code>')
                ->atPath('title')
                ->addViolation();
        }
    }

    /**
     * @Assert\Callback
     */
    public function validateOrganizers(ExecutionContext $context)
    {
        $orgIds = $this->getOrganizerIds();
        $numEmpty = count($orgIds) - count(array_filter($orgIds));
        if ($numEmpty > 0) {
            $context->buildViolation('error-usernames')
                ->setParameter(0, $numEmpty)
                ->atPath('organizers')
                ->addViolation();
        }
    }

    /**************
     * ORGANIZERS *
     **************/

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
        return array_map(function ($organizer) {
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
     * Sort the organizers collection alphabetically, and put the organizer
     * with the given username ($primary) first.
     * @param string $primary Username of organizer who should come first.
     */
    public function sortOrganizers($primary)
    {
        $primaryOrg = null;
        $nonPrimaryOrgs = [];

        foreach ($this->getOrganizers() as $organizer) {
            if ($organizer->getUsername() === $primary) {
                $primaryOrg = $organizer;
            } else {
                $nonPrimaryOrgs[] = $organizer;
            }
        }

        usort($nonPrimaryOrgs, function ($a, $b) {
            return strnatcmp($a->getUsername(), $b->getUsername());
        });

        $this->organizers = new ArrayCollection(
            array_merge([$primaryOrg], $nonPrimaryOrgs)
        );
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

    /**********
     * EVENTS *
     **********/

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
