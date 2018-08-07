<?php
/**
 * This file contains only the OrganizerTest class.
 */

namespace Tests\AppBundle\Model;

use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the Organizer class.
 */
class OrganizerTest extends GrantMetricsTestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        static::assertNull($organizer->getId());
        static::assertEquals(50, $organizer->getUserId());
    }

    /**
     * Test adding and removing programs.
     */
    public function testAddRemoveProgram()
    {
        $organizer = new Organizer(50);

        static::assertEquals(0, count($organizer->getPrograms()));

        // Add a program.
        $program = new Program($organizer);
        $organizer->addProgram($program);

        // Additional program.
        $program2 = new Program($organizer);
        $organizer->addProgram($program);

        // Try adding the same one, which shouldn't duplicate.
        $organizer->addProgram($program2);
        static::assertEquals(2, count($organizer->getPrograms()));

        // Removing the program.
        $organizer->removeProgram($program);
        static::assertEquals(1, count($organizer->getPrograms()));

        // Double-remove shouldn't error out.
        $organizer->removeProgram($program);
    }

    /**
     * Basic setters.
     */
    public function testSetters()
    {
        $organizer = new Organizer(50);

        $organizer->setUsername('MusikAnimal');
        $organizer->setUserId(123);

        static::assertFalse($organizer->exists());
        static::assertEquals(123, $organizer->getUserId());
        static::assertEquals('MusikAnimal', $organizer->getUsername());
    }
}
