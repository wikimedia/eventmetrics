<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Event;
use App\Model\EventWiki;
use App\Model\EventWikiStat;
use App\Model\Organizer;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;
use DateTime;
use InvalidArgumentException;

/**
 * Tests for the EventWikiStat class.
 * @covers \App\Model\EventWikiStat
 */
class EventWikiStatTest extends EventMetricsTestCase {
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
		$eventWiki = new EventWiki( $event );

		$ews = new EventWikiStat( $eventWiki, 'pages-created', 50 );

		// Getters.
		static::assertEquals( $event, $ews->getEvent() );
		static::assertEquals( $eventWiki, $ews->getWiki() );
		static::assertEquals( 'pages-created', $ews->getMetric() );
		static::assertEquals( 50, $ews->getValue() );
		static::assertNull( $ews->getOffset() );

		// Make sure the association was made on the Event object, too.
		static::assertEquals( $ews, $eventWiki->getStatistics()[0] );

		// Invalid metric.
		$this->expectException( InvalidArgumentException::class );
		new EventWikiStat( $eventWiki, 'invalid', 30 );
	}
}
