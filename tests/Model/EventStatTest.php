<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Event;
use App\Model\EventStat;
use App\Model\Organizer;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;
use DateTime;
use InvalidArgumentException;

/**
 * Tests for the EventStat class.
 * @covers \App\Model\EventStat
 */
class EventStatTest extends EventMetricsTestCase {
	/**
	 * Tests constructor and basic getters.
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

		$eventStat = new EventStat( $event, 'retention', 50, 10 );

		// Getters.
		static::assertEquals( $event, $eventStat->getEvent() );
		static::assertEquals( 'retention', $eventStat->getMetric() );
		static::assertEquals( 50, $eventStat->getValue() );
		static::assertEquals( 10, $eventStat->getOffset() );

		// Make sure the association was made on the Event object, too.
		static::assertEquals( $eventStat, $event->getStatistics()[0] );

		// Invalid metric.
		$this->expectException( InvalidArgumentException::class );
		new EventStat( $event, 'invalid', 30 );
	}
}
