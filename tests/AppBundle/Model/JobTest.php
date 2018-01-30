<?php
/**
 * This file contains only the JobTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Job;
use AppBundle\Model\Event;
use AppBundle\Model\Program;
use AppBundle\Model\Organizer;
use DateTime;

/**
 * Tests for the Job class.
 */
class JobTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests constructor and basic getters/setters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $event = new Event(
            $program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $job = new Job($event);
        $job->setStarted();

        // Getters.
        $this->assertEquals($event, $job->getEvent());
        $this->assertTrue($job->getStarted());

        // Record hasn't been persisted yet.
        $this->assertNull($job->getId());
        $this->assertNull($job->getSubmitted());

        // Can't re-add to the Event.
        $this->assertEquals(1, $event->getNumJobs());
        $event->addJob($job);
        $this->assertEquals(1, $event->getNumJobs());
    }
}
