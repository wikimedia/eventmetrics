<?php
/**
 * This file contains only the OrganizerTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;

/**
 * Tests for the Organizer class.
 */
class OrganizerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        $this->assertEquals(50, $organizer->getUserId());
    }

    /**
     * Test adding and removing programs.
     */
    public function testAddRemoveProgram()
    {
        $organizer = new Organizer(50);

        $this->assertEquals(0, count($organizer->getPrograms()));

        // Add a program.
        $program = new Program('Test program');
        $organizer->addProgram($program);

        $this->assertEquals($program, $organizer->getPrograms()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $organizer->addProgram($program);
        $this->assertEquals(1, count($organizer->getPrograms()));

        // Removing the program.
        $organizer->removeProgram($program);
        $this->assertEquals(0, count($organizer->getPrograms()));

        // Double-remove shouldn't error out.
        $organizer->removeProgram($program);
    }
}
