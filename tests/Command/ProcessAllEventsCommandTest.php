<?php declare( strict_types=1 );

namespace App\Tests\Command;

use App\Command\ProcessAllEventsCommand;
use App\DataFixtures\ORM\LoadFixtures;
use App\Model\Event;
use App\Model\EventWiki;
use App\Model\Participant;
use App\Model\Program;
use App\Repository\JobRepository;
use App\Repository\ProgramRepository;
use App\Tests\Controller\DatabaseAwareWebTestCase;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for ProcessAllEventsCommand.
 * @covers \App\Command\ProcessAllEventsCommand
 * @group replicas
 */
class ProcessAllEventsCommandTest extends DatabaseAwareWebTestCase {
	/** @var CommandTester|null */
	private ?CommandTester $commandTester;

	/** @var Program|null */
	private ?Program $program;

	public function setUp(): void {
		parent::setUp();

		// Load basic fixtures containing the example program.
		$this->addFixture( new LoadFixtures() );
		$this->executeFixtures();

		$this->program = static::getContainer()->get( ProgramRepository::class )
			->findOneBy( [ 'title' => 'My_fun_program' ] );

		/** @var Command $command */
		$command = static::getContainer()->get( ProcessAllEventsCommand::class );
		$this->commandTester = new CommandTester( $command );
	}

	/**
	 * Create some sample Events and run the command, making sure the Jobs were created.
	 */
	public function testProcessAllEvents(): void {
		// Create a valid Event with User:Example as a participant.
		$validEvent = new Event(
			$this->program,
			'Valid event',
			new DateTime( '2015-01-01' ),
			new DateTime( '2015-01-02' )
		);
		new Participant( $validEvent, 27666025 );
		new EventWiki( $validEvent, 'en.wikipedia' );
		$this->entityManager->persist( $validEvent );

		// An invalid Event for which a Job should not be created.
		$invalidEvent = new Event(
			$this->program,
			'Invalid event',
			new DateTime( '2050-01-01' ),
			new DateTime( '2050-01-02' )
		);
		$this->entityManager->persist( $invalidEvent );

		$this->entityManager->flush();

		// Execute the Command. We use the --no-spawn command so the Job records will remain for testing purposes.
		$this->commandTester->execute( [ '--no-spawn' => true ] );

		// Jobs should be created for only one Event.
		$jobs = static::getContainer()->get( JobRepository::class )->findAll();

		static::assertCount( 1, $jobs );
	}
}
