<?php
/**
 * This file contains only the EventTest class.
 */

namespace Tests\AppBundle\Model;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use AppBundle\Model\Participant;
use DateTime;

/**
 * Tests for the Event class.
 */
class EventTest extends KernelTestCase
{
    /** @var Program The Program that the Event is part of. */
    protected $program;

    public function setUp()
    {
        $organizer = new Organizer(50);
        $this->program = new Program($organizer);
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
        $this->assertNull($event->getId());

        $this->assertEquals($this->program, $event->getProgram());
        $this->assertEquals('My_program', $event->getTitle());
        $this->assertEquals('My program', $event->getDisplayTitle());
        $this->assertEquals('America/New_York', $event->getTimezone());
        $this->assertEquals('America/New York', $event->getDisplayTimezone());

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
            new \DateTime('2017-03-01')
        );
        $this->assertEquals(new \DateTime('2017-01-01'), $event->getStart());
        $this->assertEquals(new \DateTime('2017-03-01'), $event->getEnd());

        // Date types reversed.
        $event2 = new Event(
            $this->program,
            'My program',
            new \DateTime('2017-03-01'),
            '2017-04-01'
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

        // Changing the 'updated' attribute.
        $datetime = new DateTime('2017-01-01');
        $event->setUpdated($datetime);
        $this->assertEquals($datetime, $event->getUpdated());
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
        $participant->setUsername('DannyH (WMF)');
        $event->addParticipant($participant);

        $this->assertEquals($participant, $event->getParticipants()[0]);
        $this->assertEquals(['DannyH (WMF)'], $event->getParticipantNames());

        // Try adding the same one, which shouldn't duplicate.
        $event->addParticipant($participant);
        $this->assertEquals(1, $event->getNumParticipants());

        // Removing the event.
        $event->removeParticipant($participant);
        $this->assertEquals(0, $event->getNumParticipants());

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

    /**
     * Tests the validators on the model.
     */
    public function testValidations()
    {
        $organizer = new Organizer('MusikAnimal');
        $organizer->setUserId(50);
        $program = new Program($organizer);
        $event = new Event($program);
        $event->setTitle('delete');

        self::bootKernel();
        $validator = static::$kernel->getContainer()->get('validator');

        $errors = $validator->validate($event);

        $this->assertEquals(
            'error-title-reserved',
            $errors->get(0)->getMessage()
        );
    }
}
