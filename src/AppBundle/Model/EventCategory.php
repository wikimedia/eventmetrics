<?php
/**
 * This file contains only the EventCategory class.
 */

declare(strict_types=1);

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An EventCategory is a wiki category tied to an Event.
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventCategoryRepository")
 * @ORM\Table(
 *     name="event_category",
 *     indexes={
 *         @ORM\Index(name="ec_event", columns={"ec_event_id"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ec_event_domains", columns={"ec_event_id", "ec_title", "ec_domain"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class EventCategory
{
    /**
     * The Category namespace ID.
     */
    const CATEGORY_NAMESPACE = 14;

    /**
     * @ORM\Id
     * @ORM\Column(name="ec_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Unique ID of the EventCategory.
     */
    protected $id;

    /**
     * Many EventCategory's belong to one Event.
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="categories")
     * @ORM\JoinColumn(name="ec_event_id", referencedColumnName="event_id", nullable=false)
     * @var Event Event this EventCategory belongs to.
     */
    protected $event;

    /**
     * @ORM\Column(name="ec_title", type="string", length=255)
     * @Assert\Type("string")
     * @Assert\Length(max=255)
     * @var string Category title.
     */
    protected $title;

    /**
     * @ORM\Column(name="ec_domain", type="string", length=255, nullable=false)
     * @Assert\Type("string")
     * @Assert\NotBlank(message="")
     * @var string Domain of the wiki, without the .org.
     */
    protected $domain;

    /**
     * EventCategory constructor.
     * @param Event $event
     * @param string $title
     * @param string $domain Without .org, such as en.wikipedia
     */
    public function __construct(Event $event, $title, $domain)
    {
        $this->event = $event;
        $this->event->addCategory($this);
        $this->setTitle($title);
        $this->domain = $domain;
    }

    /**
     * Get the ID of the EventCategory.
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the Event this EventCategory belongs to.
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * Set the Event this EventCategory belongs to.
     * @param Event $event
     */
    public function setEvent(Event $event): void
    {
        $this->event = $event;
    }

    /**
     * Get the wiki domain this EventCategory applies to.
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Set the wiki domain this EventCategory applies to.
     * @param string $domain
     */
    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * Set the title of the category.
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        // Use underscores instead of spaces, as they will have to be when querying the replicas.
        $this->title = str_replace(' ', '_', trim($title));
    }

    /**
     * Get the title of the category.
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the display variant of the program title.
     * @return string
     */
    public function getDisplayTitle(): string
    {
        return str_replace('_', ' ', $this->title);
    }
}
