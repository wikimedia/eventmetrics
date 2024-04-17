<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Event;
use App\Model\EventCategory;
use App\Model\EventWiki;
use App\Model\EventWikiStat;
use App\Model\Organizer;
use App\Model\Participant;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Tests for the EventWiki class.
 * @covers \App\Model\EventWiki
 */
class EventWikiTest extends EventMetricsTestCase {
	/** @var Event The Event that the EventWiki is part of. */
	protected Event $event;

	public function setUp(): void {
		parent::setUp();

		$organizer = new Organizer( 50 );
		$program = new Program( $organizer );
		$this->event = new Event(
			$program,
			'  My program  ',
			'2017-01-01',
			new DateTime( '2017-03-01' ),
			'America/New_York'
		);
	}

	/**
	 * Tests constructor and basic getters.
	 */
	public function testConstructor(): void {
		$wiki = new EventWiki( $this->event, 'test.wikipedia' );

		// Basic getters.
		static::assertEquals( $this->event, $wiki->getEvent() );
		static::assertEquals( 'test.wikipedia', $wiki->getDomain() );

		static::assertInstanceOf(
			'Doctrine\Common\Collections\ArrayCollection',
			$wiki->getStatistics()
		);

		// Make sure the association was made on the Event object, too.
		static::assertEquals( $wiki, $this->event->getWikis()[0] );
	}

	/**
	 * Test adding and removing statistics.
	 */
	public function testAddRemoveStatistics(): void {
		$wiki = new EventWiki( $this->event, 'test.wikipedia' );

		static::assertCount( 0, $wiki->getStatistics() );

		// Add an EventWikiStat.
		$ews = new EventWikiStat( $wiki, 'pages-improved', 50 );
		$wiki->addStatistic( $ews );

		static::assertEquals( $ews, $wiki->getStatistics()[0] );
		static::assertEquals( $ews, $wiki->getStatistic( 'pages-improved' ) );

		// Try adding the same one, which shouldn't duplicate.
		$wiki->addStatistic( $ews );
		static::assertCount( 1, $wiki->getStatistics() );

		// Removing the statistic.
		$wiki->removeStatistic( $ews );
		static::assertCount( 0, $wiki->getStatistics() );

		// Double-remove shouldn't error out.
		$wiki->removeStatistic( $ews );

		// Clearing statistics.
		$wiki->addStatistic( $ews );
		static::assertCount( 1, $wiki->getStatistics() );
		$wiki->clearStatistics();
		static::assertCount( 0, $wiki->getStatistics() );
	}

	/**
	 * Test methods involving wiki families.
	 */
	public function testWikiFamilies(): void {
		// Not a wiki family.
		$wiki = new EventWiki( $this->event, 'test.wikipedia' );
		static::assertFalse( $wiki->isFamilyWiki() );
		static::assertEquals( 'wikipedia', $wiki->getFamilyName() );
		static::assertEquals( new ArrayCollection( [] ), $wiki->getChildWikis() );
		static::assertFalse( $wiki->isChildWiki() );

		// Create a *.wikipedia EventWiki, making the above EventWiki a child.
		$wikiFam = new EventWiki( $this->event, '*.wikipedia' );
		static::assertTrue( $wikiFam->isFamilyWiki() );
		static::assertEquals( 'wikipedia', $wikiFam->getFamilyName() );
		static::assertEquals( [ $wiki ], $wikiFam->getChildWikis()->toArray() );
		static::assertTrue( $wiki->isChildWiki() );

		// Add Commons, which should be treated as an orphan wiki.
		$commons = new EventWiki( $this->event, 'commons.wikimedia' );
		static::assertFalse( $commons->isFamilyWiki() );
		static::assertEquals( 'commons', $commons->getFamilyName() );
		static::assertEquals( new ArrayCollection( [] ), $commons->getChildWikis() );
		static::assertFalse( $commons->isChildWiki() );

		$familyDomains = $this->event->getFamilyWikis()->map( static function ( EventWiki $wiki ) {
			return $wiki->getDomain();
		} )->toArray();
		static::assertEquals( [ '*.wikipedia' ], array_values( $familyDomains ) );

		// Commons should not be a child of *.wikipedia
		static::assertFalse( $wikiFam->getChildWikis()->contains( $commons ) );

		// Clearing child wikis from the Event should only remove test.wikipedia
		$this->event->clearChildWikis();
		$domains = $this->event->getWikis()->map( static function ( EventWiki $wiki ) {
			return $wiki->getDomain();
		} )->toArray();
		static::assertEquals( [ '*.wikipedia', 'commons.wikimedia' ], array_values( $domains ) );
	}

	/**
	 * Test methods involving page IDs.
	 */
	public function testPages(): void {
		$wiki = new EventWiki( $this->event, 'test.wikipedia' );

		// Basic setter/getter.
		$wiki->setPagesCreated( [ 1, 2, 3 ] );
		static::assertEquals( [ 1, 2, 3 ], $wiki->getPagesCreated() );
		$wiki->setPagesImproved( [ 4, 5, 6 ] );
		static::assertEquals( [ 4, 5, 6 ], $wiki->getPagesImproved() );
		static::assertEquals( [ 1, 2, 3, 4, 5, 6 ], $wiki->getPages() );

		// Make sure that empty strings are removed, and strings are cast to integers.
		$wiki->setPagesCreated( [ '' ] );
		static::assertEquals( [], $wiki->getPagesCreated() );
		$wiki->setPagesCreated( [ '', '123', null, '', 456, 'foo' ] );
		static::assertEquals( [ 123, 456 ], $wiki->getPagesCreated() );
	}

	/**
	 * @covers \App\Model\EventWiki::isValid()
	 * @covers \App\Model\EventWiki::canHaveFilesUploaded()
	 */
	public function testValidity(): void {
		$enwiki = new EventWiki( $this->event, 'en.wikipedia' );

		// No participants, no categories.
		static::assertFalse( $enwiki->isValid() );
		static::assertFalse( $enwiki->canHaveFilesUploaded() );

		// Add participant.
		$participant = new Participant( $this->event );
		static::assertTrue( $enwiki->isValid() );
		static::assertTrue( $enwiki->canHaveFilesUploaded() );

		// Remove participant, add category.
		$this->event->removeParticipant( $participant );
		new EventCategory( $this->event, 'Parks in Brooklyn', 'en.wikipedia' );
		static::assertTrue( $enwiki->isValid() );
		// Can't have files uploaded if we only have a category.
		static::assertFalse( $enwiki->canHaveFilesUploaded() );

		// Similar tests but on Commons (where it always can have files uploaded).
		$commons = new EventWiki( $this->event, 'commons.wikimedia' );

		// No participants, no categories.
		static::assertFalse( $commons->isValid() );
		static::assertFalse( $commons->canHaveFilesUploaded() );

		// Add a category.
		new EventCategory( $this->event, 'Test category', 'commons.wikimedia' );
		static::assertTrue( $commons->isValid() );
		static::assertTrue( $commons->canHaveFilesUploaded() );
	}
}
