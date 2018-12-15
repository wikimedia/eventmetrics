<?php
/**
 * This file contains only the JobTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Event;
use AppBundle\Model\Job;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * Tests for the Job class.
 */
class JobTest extends EventMetricsTestCase
{
    /**
     * Tests constructor and basic getters/setters.
     */
    public function testConstructor(): void
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
        $job->setStatus(Job::STATUS_STARTED);

        // Getters.
        static::assertEquals($event, $job->getEvent());
        static::assertTrue($job->hasStarted());
        static::assertEquals(Job::STATUS_STARTED, $job->getStatus());
        static::assertFalse($job->hasFailed());

        $job->setStatus(Job::STATUS_FAILED_TIMEOUT);
        static::assertTrue($job->hasFailed());

        // Record hasn't been persisted yet.
        static::assertNull($job->getId());
        static::assertNull($job->getSubmitted());

        // Can't re-add to the Event.
        static::assertEquals(1, $event->getNumJobs());
        $event->addJob($job);
        static::assertEquals(1, $event->getNumJobs());
    }
}
