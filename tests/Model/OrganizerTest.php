<?php declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Organizer;
use App\Model\Program;
use App\Tests\EventMetricsTestCase;

/**
 * Tests for the Organizer class.
 * @covers \App\Model\Organizer
 */
class OrganizerTest extends EventMetricsTestCase {
	/**
	 * Tests constructor and basic getters.
	 */
	public function testConstructor(): void {
		$organizer = new Organizer( 50 );
		static::assertNull( $organizer->getId() );
		static::assertEquals( 50, $organizer->getUserId() );
	}

	/**
	 * Test adding and removing programs.
	 */
	public function testAddRemoveProgram(): void {
		$organizer = new Organizer( 50 );

		static::assertCount( 0, $organizer->getPrograms() );

		// Add a program.
		$program = new Program( $organizer );
		$organizer->addProgram( $program );

		// Additional program.
		$program2 = new Program( $organizer );
		$organizer->addProgram( $program );

		// Try adding the same one, which shouldn't duplicate.
		$organizer->addProgram( $program2 );
		static::assertCount( 2, $organizer->getPrograms() );

		// Removing the program.
		$organizer->removeProgram( $program );
		static::assertCount( 1, $organizer->getPrograms() );

		// Double-remove shouldn't error out.
		$organizer->removeProgram( $program );
	}

	/**
	 * Basic setters.
	 */
	public function testSetters(): void {
		$organizer = new Organizer( 50 );

		$organizer->setUsername( 'MusikAnimal' );
		$organizer->setUserId( 123 );

		static::assertFalse( $organizer->exists() );
		static::assertEquals( 123, $organizer->getUserId() );
		static::assertEquals( 'MusikAnimal', $organizer->getUsername() );
	}
}
