<?php
/**
 * This file contains only the EventTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Program;
use AppBundle\Model\Participant;

/**
 * Tests for the Event class.
 */
class EventTest extends PHPUnit_Framework_TestCase
{
    /** @var Program The Program that the Event is part of. */
    protected $program;

    public function setUp()
    {
        $this->program = new Program('Test program');
    }
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $this->assertEquals($this->program, $event->getProgram());
        $this->assertEquals('My program', $event->getTitle());
        $this->assertEquals('America/New_York', $event->getTimezone());

        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $event->getParticipants()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $event->getStatistics()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $event->getWikis()
        );
    }

    /**
     * Tests that dates in the constructor were stored properly in the class.
     */
    public function testDates()
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );
        $this->assertEquals(new \DateTime('2017-01-01'), $event->getStart());
        $this->assertEquals(new \DateTime('2017-03-01'), $event->getEnd());

        // Date types reversed.
        $event2 = new Event(
            $this->program,
            'My program',
            new \DateTime('2017-03-01'),
            '2017-04-01',
            'America/New_York'
        );
        $this->assertEquals(new \DateTime('2017-03-01'), $event2->getStart());
        $this->assertEquals(new \DateTime('2017-04-01'), $event2->getEnd());
    }

    /**
     * Test adding and removing statistics.
     */
    public function testAddRemoveStatistics()
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $this->assertEquals(0, count($event->getStatistics()));

        // Add an EventStat.
        $eventStat = new EventStat($event, 'retention', 50);
        $event->addStatistic($eventStat);

        $this->assertEquals($eventStat, $event->getStatistics()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $event->addStatistic($eventStat);
        $this->assertEquals(1, count($event->getStatistics()));

        // Removing the statistic.
        $event->removeStatistic($eventStat);
        $this->assertEquals(0, count($event->getStatistics()));

        // Double-remove shouldn't error out.
        $event->removeStatistic($eventStat);
    }

    /**
     * Test adding and removing participants.
     */
    public function testAddRemoveParticipant()
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $this->assertEquals(0, count($event->getParticipants()));

        // Add a Participant.
        $participant = new Participant($event, 50);
        $event->addParticipant($participant);

        $this->assertEquals($participant, $event->getParticipants()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $event->addParticipant($participant);
        $this->assertEquals(1, count($event->getParticipants()));

        // Removing the event.
        $event->removeParticipant($participant);
        $this->assertEquals(0, count($event->getParticipants()));

        // Double-remove shouldn't error out.
        $event->removeParticipant($participant);
    }

    /**
     * Test adding and removing wikis.
     */
    public function testAddRemoveWiki()
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $this->assertEquals(0, count($event->getParticipants()));

        // Add a wiki.
        $wiki = new EventWiki($event, 'testwiki');
        $event->addWiki($wiki);

        $this->assertEquals($wiki, $event->getWikis()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $event->addWiki($wiki);
        $this->assertEquals(1, count($event->getWikis()));

        // Removing the wiki.
        $event->removeWiki($wiki);
        $this->assertEquals(0, count($event->getWikis()));

        // Double-remove shouldn't error out.
        $event->removeWiki($wiki);
    }
}
