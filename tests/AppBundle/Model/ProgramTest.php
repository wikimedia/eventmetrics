<?php
/**
 * This file contains only the ProgramTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Program;
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
        $program = new Program('  Test program  ');
        $this->assertEquals('Test program', $program->getTitle());
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $program->getEvents()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $program->getOrganizers()
        );
    }

    /**
     * Test adding and removing organizers.
     */
    public function testAddRemoveOrganizer()
    {
        $program = new Program('Test program');

        $this->assertEquals(0, count($program->getOrganizers()));

        // Add an organizer.
        $organizer = new Organizer(50);
        $program->addOrganizer($organizer);

        $this->assertEquals($organizer, $program->getOrganizers()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $program->addOrganizer($organizer);
        $this->assertEquals(1, count($program->getOrganizers()));

        // Removing the organizer.
        $program->removeOrganizer($organizer);
        $this->assertEquals(0, count($program->getOrganizers()));
    }

    /**
     * Test adding and removing events.
     */
    public function testAddRemoveEvent()
    {
        $program = new Program('Test program');

        $this->assertEquals(0, count($program->getEvents()));

        // Add an event.
        $event = new Event($program, 'My program');
        $program->addEvent($event);

        $this->assertEquals($event, $program->getEvents()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $program->addEvent($event);
        $this->assertEquals(1, count($program->getEvents()));

        // Removing the event.
        $program->removeEvent($event);
        $this->assertEquals(0, count($program->getEvents()));

        // Double-remove shouldn't error out.
        $program->removeEvent($event);
    }
}
