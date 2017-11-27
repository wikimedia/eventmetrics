<?php
/**
 * This file contains only the ProgramTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Program;
use AppBundle\Repository\ProgramRepository;
use AppBundle\Model\Event;
use AppBundle\Model\Organizer;

/**
 * Tests for the Program class.
 */
class ProgramTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $this->assertEquals(1, count($program->getOrganizers()));
        $this->assertEquals($organizer, $program->getOrganizers()[0]);
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $program->getEvents()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $program->getOrganizers()
        );
        $this->assertNull($program->getId());
    }

    /**
     * Test adding and removing organizers.
     */
    public function testAddRemoveOrganizer()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);

        // Add another organizer by user ID.
        $organizer2 = new Organizer(100);
        $program->addOrganizer($organizer2);

        $this->assertEquals($organizer, $program->getOrganizers()[0]);
        $this->assertEquals($organizer2, $program->getOrganizers()[1]);

        // Try adding the same one, which shouldn't duplicate.
        $program->addOrganizer($organizer);
        $this->assertEquals(2, $program->getNumOrganizers());
        $this->assertEquals(
            [$organizer, $organizer2],
            $program->getOrganizers()->toArray()
        );
        $this->assertEquals(
            [50, 100],
            $program->getOrganizerIds()
        );

        // Removing the organizer.
        $program->removeOrganizer($organizer2);
        $this->assertEquals(1, $program->getNumOrganizers());
        $this->assertEquals(
            [$organizer],
            $program->getOrganizers()->toArray()
        );
        $this->assertEquals(
            [50],
            $program->getOrganizerIds()
        );

        // Double-remove shouldn't error out.
        $program->removeOrganizer($organizer2);
    }

    /**
     * Test setting organizers by username.
     */
    public function testSetOrganizerNames()
    {
        $organizer = new Organizer('Foo');
        $program = new Program($organizer);
        $program->setOrganizerNames(['Foo', 'Bar', 'Baz']);
    }

    /**
     * Test adding and removing events.
     */
    public function testAddRemoveEvent()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);

        $this->assertEquals(0, count($program->getEvents()));

        // Add an event.
        $event = new Event($program, 'My program');
        $program->addEvent($event);

        $this->assertEquals($event, $program->getEvents()[0]);
        $this->assertEquals(1, $program->getNumEvents());

        // Try adding the same one, which shouldn't duplicate.
        $program->addEvent($event);
        $this->assertEquals(1, count($program->getEvents()));

        // Removing the event.
        $program->removeEvent($event);
        $this->assertEquals(0, count($program->getEvents()));

        // Double-remove shouldn't error out.
        $program->removeEvent($event);
    }

    /**
     * Normalized slug of the program.
     */
    public function testSanitizeTitle()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $program->setTitle(" My fun program 5 ");
        $this->assertEquals('My_fun_program_5', $program->getTitle());
        $this->assertEquals('My fun program 5', $program->getDisplayTitle());
    }
}
