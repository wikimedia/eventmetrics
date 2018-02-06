<?php
/**
 * This file contains only the EventWiki class.
 */

namespace AppBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An EventWiki belongs to an Event, and also connects an EventStat
 * to a specific wiki and event.
 * @ORM\Entity
 * @ORM\Table(
 *     name="event_wiki",
 *     indexes={
 *         @ORM\Index(name="ew_event", columns={"ew_event_id"}),
 *         @ORM\Index(name="ew_domain", columns={"ew_domain"}),
 *         @ORM\Index(name="ew_event_domain", columns={"ew_event_id", "ew_domain"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ew_event_wiki", columns={"ew_event_id", "ew_domain"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventWikiRepository")
 */
class EventWiki
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
     * @ORM\Column(name="ew_domain", type="string", length=255, nullable=false)
     * @Assert\NotBlank(message="")
     * @var string Domain of the wiki, without the .org.
     */
    protected $domain;

    /**
     * One EventWiki has many EventStats.
     * @ORM\OneToMany(targetEntity="EventWikiStat", mappedBy="wiki", orphanRemoval=true)
     * @var ArrayCollection|EventStat[] Statistics for this EventWiki.
     */
    protected $stats;

    /**
     * Event constructor.
     * @param Event $event Event that this EventWiki belongs to.
     * @param string $domain Domain name of the wiki, without the .org.
     */
    public function __construct(Event $event, $domain = null)
    {
        $this->event = $event;
        $this->event->addWiki($this);
        $this->domain = $domain;
        $this->stats = new ArrayCollection();
    }

    /**
     * Get the Event this EventWiki belongs to.
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Get the domain name.
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**************
     * STATISTICS *
     **************/

    /**
     * Get statistics about this EventWiki.
     * @return ArrayCollection|EventWikiStat[]
     */
    public function getStatistics()
    {
        return $this->stats;
    }

    /**
     * Add an EventWikiStat to this EventWiki.
     * @param EventWikiStat $eventWikiStat
     */
    public function addStatistic(EventWikiStat $eventWikiStat)
    {
        if ($this->stats->contains($eventWikiStat)) {
            return;
        }
        $this->stats->add($eventWikiStat);
    }

    /**
     * Remove an EventWikiStat from this EventWiki.
     * @param EventWikiStat $eventWikiStat
     */
    public function removeStatistic(EventWikiStat $eventWikiStat)
    {
        if (!$this->stats->contains($eventWikiStat)) {
            return;
        }
        $this->stats->removeElement($eventWikiStat);
    }
}
