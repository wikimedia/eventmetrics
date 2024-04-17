<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Event;
use App\Model\EventCategory;
use App\Model\EventWiki;
use App\Model\Organizer;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;
use DateTime;

/**
 * Class EventCategoryTest
 * @covers \App\Model\EventCategory
 */
class EventCategoryTest extends EventMetricsTestCase {
	/** @var Event The Event that the EventCategory belongs to. */
	protected Event $event;

	public function setUp(): void {
		parent::setUp();

		$organizer = new Organizer( 50 );
		$program = new Program( $organizer );
		$this->event = new Event(
			$program,
			'My event',
			'2017-01-01',
			new DateTime( '2017-03-01' ),
			'America/New_York'
		);
	}

	/**
	 * Tests constructor and basic getters.
	 */
	public function testConstructor(): void {
		$eventCategory = new EventCategory( $this->event, ' Foo_bar ', 'test.wikipedia' );

		static::assertCount( 1, $this->event->getCategories() );
		static::assertSame( 1, $this->event->getNumCategories() );

		// Getters.
		static::assertEquals( $this->event, $eventCategory->getEvent() );
		static::assertEquals( 'Foo bar', $eventCategory->getTitle() );
	}

	/**
	 * Test adding and removing categories.
	 */
	public function testAddRemoveCategories(): void {
		static::assertCount( 0, $this->event->getCategories() );

		// Add an EventCategory.
		$cat = new EventCategory( $this->event, 'Foo bar', 'test.wikipedia' );

		static::assertEquals( $cat, $this->event->getCategories()[0] );

		// Manually set the Event, which should make no difference.
		$cat->setEvent( $this->event );
		static::assertEquals( $cat, $this->event->getCategories()[0] );

		// Try adding the same one, which shouldn't duplicate.
		$this->event->addCategory( $cat );
		static::assertCount( 1, $this->event->getCategories() );

		// Getting titles, going by associated wiki.
		$wiki = new EventWiki( $this->event, 'test.wikipedia' );
		static::assertEquals( [ 'Foo_bar' ], $this->event->getCategoryTitlesForWiki( $wiki ) );

		// Removing the category.
		$this->event->removeCategory( $cat );
		static::assertCount( 0, $this->event->getCategories() );

		// Double-remove shouldn't error out.
		$this->event->removeCategory( $cat );

		// Clearing categories.
		$this->event->addCategory( $cat );
		static::assertCount( 1, $this->event->getCategories() );
		$this->event->clearCategories();
		static::assertCount( 0, $this->event->getCategories() );
	}
}
