<?php
/**
 * This file contains only the ProgramTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Program;

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
}
