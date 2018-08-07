<?php
/**
 * This file contains only the JobTest class.
 */

namespace Tests\AppBundle\Model;

use AppBundle\Model\Job;
use AppBundle\Model\Event;
use AppBundle\Model\Program;
use AppBundle\Model\Organizer;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the Job class.
 */
class JobTest extends GrantMetricsTestCase
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
        static::assertEquals($event, $job->getEvent());
        static::assertTrue($job->getStarted());

        // Record hasn't been persisted yet.
        static::assertNull($job->getId());
        static::assertNull($job->getSubmitted());

        // Can't re-add to the Event.
        static::assertEquals(1, $event->getNumJobs());
        $event->addJob($job);
        static::assertEquals(1, $event->getNumJobs());
    }
}
