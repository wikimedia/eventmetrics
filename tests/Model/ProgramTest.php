<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Event;
use App\Model\EventStat;
use App\Model\Organizer;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Tests for the Program class.
 * @covers \App\Model\Program
 */
class ProgramTest extends EventMetricsTestCase {

	/** @var Organizer The Organizer of the Program. */
	protected Organizer $organizer;

	/** @var Program The test Program itself. */
	protected Program $program;

	/**
	 * Create test Organizer and Program.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->organizer = new Organizer( 50 );
		$this->program = new Program( $this->organizer );
	}

	/**
	 * Tests constructor and basic getters.
	 */
	public function testConstructor(): void {
		static::assertCount( 1, $this->program->getOrganizers() );
		static::assertEquals( $this->organizer, $this->program->getOrganizers()[0] );
		static::assertInstanceOf(
			'Doctrine\Common\Collections\ArrayCollection',
			$this->program->getEvents()
		);
		static::assertInstanceOf(
			'Doctrine\Common\Collections\ArrayCollection',
			$this->program->getOrganizers()
		);
		static::assertNull( $this->program->getId() );
	}

	/**
	 * Test adding and removing organizers.
	 */
	public function testAddRemoveOrganizer(): void {
		// Add another organizer by user ID.
		$organizer2 = new Organizer( 100 );
		$this->program->addOrganizer( $organizer2 );

		static::assertEquals( $this->organizer, $this->program->getOrganizers()[0] );
		static::assertEquals( $organizer2, $this->program->getOrganizers()[1] );

		// Try adding the same one, which shouldn't duplicate.
		$this->program->addOrganizer( $this->organizer );
		static::assertEquals( 2, $this->program->getNumOrganizers() );
		static::assertEquals(
			[ $this->organizer, $organizer2 ],
			$this->program->getOrganizers()->toArray()
		);
		static::assertEquals(
			[ 50, 100 ],
			$this->program->getOrganizerIds()
		);

		// Removing the organizer.
		$this->program->removeOrganizer( $organizer2 );
		static::assertSame( 1, $this->program->getNumOrganizers() );
		static::assertEquals(
			[ $this->organizer ],
			$this->program->getOrganizers()->toArray()
		);
		static::assertEquals(
			[ 50 ],
			$this->program->getOrganizerIds()
		);

		// Double-remove shouldn't error out.
		$this->program->removeOrganizer( $organizer2 );
	}

	/**
	 * Test setting organizers by username.
	 */
	public function testSetOrganizerNames(): void {
		$organizer = new Organizer( 'Foo' );
		$program = new Program( $organizer );
		$program->setOrganizerNames( [ 'Foo', 'Bar', 'Baz' ] );
		static::assertEquals(
			[ 'Foo', 'Bar', 'Baz' ],
			$program->getOrganizerNames()
		);
	}

	/**
	 * Test adding and removing events.
	 */
	public function testAddRemoveEvent(): void {
		static::assertCount( 0, $this->program->getEvents() );

		// Add an event.
		$event = new Event( $this->program, 'My fun event' );
		$this->program->addEvent( $event );

		static::assertEquals( $event, $this->program->getEvents()[0] );
		static::assertSame( 1, $this->program->getNumEvents() );

		// Should be null, since we're aren't actually flushing to the db.
		static::assertEquals( [ null ], $this->program->getEventIds() );

		// Try adding the same one, which shouldn't duplicate.
		$this->program->addEvent( $event );
		static::assertCount( 1, $this->program->getEvents() );

		// Removing the event.
		$this->program->removeEvent( $event );
		static::assertCount( 0, $this->program->getEvents() );

		// Double-remove shouldn't error out.
		$this->program->removeEvent( $event );
	}

	/**
	 * Normalized slug of the program.
	 */
	public function testSanitizeTitle(): void {
		$this->program->setTitle( " My fun program 5 " );
		static::assertEquals( 'My_fun_program_5', $this->program->getTitle() );
		static::assertEquals( 'My fun program 5', $this->program->getDisplayTitle() );
	}

	/**
	 * Tests the validators on the model.
	 * @group replicas
	 */
	public function testValidations(): void {
		$organizer = new Organizer( '' );
		$program = new Program( $organizer );
		$program->setTitle( 'Foo bar' );

		$validator = static::getContainer()->get( 'validator' );

		/** @var ConstraintViolationList $errors */
		$errors = $validator->validate( $program );

		static::assertEquals(
			'error-usernames',
			$errors->get( 0 )->getMessage()
		);
	}

	/**
	 * Test fetching statistics.
	 */
	public function testStatistics(): void {
		// Create some events with event stats.
		$event1 = new Event( $this->program, 'The Lion King' );
		$this->program->addEvent( $event1 );
		new EventStat( $event1, 'pages-improved', 5 );
		new EventStat( $event1, 'pages-created', 10 );

		$event2 = new Event( $this->program, 'Oliver & Company' );
		$this->program->addEvent( $event2 );
		new EventStat( $event2, 'pages-improved', 15 );
		new EventStat( $event2, 'pages-created', 20 );

		static::assertEquals( 20, $this->program->getStatistic( 'pages-improved' ) );
		static::assertEquals(
			[
				'pages-improved' => 20,
				'pages-created' => 30,
			],
			$this->program->getStatistics()
		);
	}

	/**
	 * Test that 4-byte characters in titles are removed. This is because we're using MySQL's utf8 encoding,
	 * which only permits 3-byte characters and issues a warning and truncates at the first longer character.
	 * @see https://phabricator.wikimedia.org/T201388
	 */
	public function testTitleWithExtendedCharacters(): void {
		$this->organizer->setUsername( 'Foo' );
		/** @var EntityManagerInterface $entityManager */
		$entityManager = static::getContainer()->get( 'doctrine' )->getManager();
		$this->program->setTitle( 'Iñtërnâtiônàlizætiønd 🙇 Iñtërnâtiônàlizætiøn' );
		$entityManager->persist( $this->program );
		$entityManager->flush();
		static::assertEquals( 'Iñtërnâtiônàlizætiønd_�_Iñtërnâtiônàlizætiøn', $this->program->getTitle() );
		static::assertEquals( 'Iñtërnâtiônàlizætiønd � Iñtërnâtiônàlizætiøn', $this->program->getDisplayTitle() );
	}
}
