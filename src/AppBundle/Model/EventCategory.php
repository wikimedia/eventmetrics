<?php
/**
 * This file contains only the EventCategory class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An EventCategory is a wiki category tied to an EventWiki, and hence a single Event.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event_category",
 *     indexes={
 *         @ORM\Index(name="ec_event_wiki", columns={"ec_event_wiki_id"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ec_wikis", columns={"ec_category_id", "ec_event_wiki_id"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventCategoryRepository")
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
     * Many EventCategory's belong to one EventWiki.
     * @ORM\ManyToOne(targetEntity="EventWiki", inversedBy="categories")
     * @ORM\JoinColumn(name="ec_event_wiki_id", referencedColumnName="ew_id", nullable=false)
     * @var EventWiki EventWiki this EventWikiCategory belongs to.
     */
    protected $wiki;

    /**
     * Associated ID of the category in the `category` table on the replicas.
     * @ORM\Column(name="ec_category_id", type="integer", nullable=false)
     * @Assert\NotBlank(message="")
     * @var int Corresponds to the `cat_id` column in `category` on the replicas.
     */
    protected $categoryId;

    /**
     * @var string Category title fetched from the ID.
     */
    protected $title;

    /**
     * EventCategory constructor.
     * @param EventWiki $wiki
     * @param int|null $categoryId
     */
    public function __construct(EventWiki $wiki, $categoryId = null)
    {
        $this->wiki = $wiki;
        $this->wiki->addCategory($this);
        $this->categoryId = $categoryId;
    }

    /**
     * Get the Event this EventWiki applies to.
     * @return Event
     */
    public function getEvent()
    {
        return $this->wiki->getEvent();
    }

    /**
     * Get the EventWiki this EventWiki applies to.
     * @return EventWiki
     */
    public function getWiki()
    {
        return $this->wiki;
    }

    /**
     * Set the ID of the category in the MediaWiki database.
     * @param int $id
     */
    public function setCategoryId($id)
    {
        $this->categoryId = $id;
    }

    /**
     * ID of the category in the MediaWiki database.
     * @return int
     */
    public function getCategoryId()
    {
        return $this->categoryId;
    }

    /**
     * Set the title of the category. This value is not persisted to the database.
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get the title of the category.
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}
