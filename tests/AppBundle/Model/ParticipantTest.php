<?php
/**
 * This file contains only the ParticipantTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Participant;
use AppBundle\Model\Event;
use AppBundle\Model\Program;

/**
 * Tests for the Participant class.
 */
class ParticipantTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $program = new Program('Test program');
        $event = new Event(
            $program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );
        $participant = new Participant($event, 50);

        $this->assertEquals(50, $participant->getUserId());
        $this->assertEquals($event, $participant->getEvent());
    }
}
