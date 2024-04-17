<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Event;
use App\Model\Job;
use App\Model\Organizer;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;
use DateTime;

/**
 * Tests for the Job class.
 * @covers \App\Model\Job
 */
class JobTest extends EventMetricsTestCase {
	/**
	 * Tests constructor and basic getters/setters.
	 */
	public function testConstructor(): void {
		$organizer = new Organizer( 50 );
		$program = new Program( $organizer );
		$event = new Event(
			$program,
			'  My program  ',
			'2017-01-01',
			new DateTime( '2017-03-01' ),
			'America/New_York'
		);

		$job = new Job( $event );
		$job->setStatus( Job::STATUS_STARTED );

		// Getters.
		static::assertEquals( $event, $job->getEvent() );
		static::assertEquals( Job::STATUS_STARTED, $job->getStatus() );
		static::assertFalse( $job->hasFailed() );

		$job->setStatus( Job::STATUS_FAILED_TIMEOUT );
		static::assertTrue( $job->hasFailed() );

		// Record hasn't been persisted yet.
		static::assertNull( $job->getId() );
		static::assertNull( $job->getSubmitted() );

		// Can't re-add to the Event.
		static::assertSame( 1, $event->getNumJobs() );
		$event->addJob( $job );
		static::assertSame( 1, $event->getNumJobs() );
	}
}
