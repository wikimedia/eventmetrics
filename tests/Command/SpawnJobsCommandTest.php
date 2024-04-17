<?php declare( strict_types=1 );

namespace App\Tests\Command;

use App\Command\SpawnJobsCommand;
use App\DataFixtures\ORM\LoadFixtures;
use App\Model\Event;
use App\Model\Job;
use App\Repository\EventRepository;
use App\Tests\EventMetricsTestCase;
use DateTime;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for the SpawnJobsCommand.
 * @covers \App\Command\SpawnJobsCommand
 * @group replicas
 */
class SpawnJobsCommandTest extends EventMetricsTestCase {

	/** @var EntityManager */
	protected EntityManager $entityManager;

	/** @var ContainerAwareLoader */
	private ContainerAwareLoader $fixtureLoader;

	/** @var CommandTester */
	private CommandTester $commandTester;

	/**
	 * Event created in the fixtures.
	 * @var Event
	 */
	private Event $event;

	public function setUp(): void {
		parent::setUp();

		/** @var EntityManager $entityManager */
		$this->entityManager = static::getContainer()->get( 'doctrine' )->getManager();

		$fixtureExecutor = new ORMExecutor(
			$this->entityManager,
			new ORMPurger( $this->entityManager )
		);

		$this->getFixtureLoader()->addFixture( new LoadFixtures( 'extended' ) );
		$fixtureExecutor->execute( $this->getFixtureLoader()->getFixtures() );

		// We need the event created in the fixtures.
		$this->event = static::getContainer()->get( EventRepository::class )
			->findOneBy( [ 'title' => 'Oliver_and_Company' ] );

		/** @var Command $command */
		$command = static::getContainer()->get( SpawnJobsCommand::class );
		$this->commandTester = new CommandTester( $command );
	}

	/**
	 * @return ContainerAwareLoader
	 */
	private function getFixtureLoader(): ContainerAwareLoader {
		if ( !isset( $this->fixtureLoader ) ) {
			$this->fixtureLoader = new ContainerAwareLoader( static::getContainer() );
		}
		return $this->fixtureLoader;
	}

	/**
	 * Start of test suite, run the command and make the assertions.
	 */
	public function testProcess(): void {
		$this->nonexistentSpec();

		// Create a Job for the Event and flush it to the database.
		$job = new Job( $this->event );
		$this->entityManager->persist( $job );
		$this->entityManager->flush();

		// We'll run some assertions on the Job class.
		$this->jobSpec( $job );

		$this->spawnSpec( $job );

		// Revive the job and run once more.
		$job->setStatus( Job::STATUS_QUEUED );
		$this->entityManager->persist( $job );
		$this->entityManager->flush();

		$this->spawnOneSpec( $job );
	}

	/**
	 * When there are no queued jobs.
	 */
	private function nonexistentSpec(): void {
		$this->commandTester->execute( [] );
		static::assertSame( 0, $this->commandTester->getStatusCode() );

		$output = $this->commandTester->getDisplay();
		static::assertStringContainsString( 'No jobs found in the queue', $output );
	}

	/**
	 * Some post-persist assertions on the Job class, since
	 * the dedicated JobTest class does not persist to the database.
	 * @param Job $job
	 */
	private function jobSpec( Job $job ): void {
		static::assertTrue( $job->getId() > 0 );
		static::assertEquals(
			( new DateTime() )->format( 'Ymd' ),
			$job->getSubmitted()->format( 'Ymd' )
		);
		static::assertTrue( $job->isBusy() );
		static::assertEquals( Job::STATUS_QUEUED, $job->getStatus() );
	}

	/**
	 * Spawning all jobs.
	 * @param Job $job
	 */
	private function spawnSpec( Job $job ): void {
		$this->commandTester->execute( [] );
		static::assertTrue( $job->isBusy() );
		$output = $this->commandTester->getDisplay();
		static::assertStringContainsString( 'Event statistics successfully saved', $output );
		static::assertSame( 0, $this->commandTester->getStatusCode() );
	}

	/**
	 * Spawning a single job.
	 * @param Job $job
	 */
	private function spawnOneSpec( Job $job ): void {
		// First try bogus job ID.
		$this->commandTester->execute( [ '--id' => 12345 ] );
		$output = $this->commandTester->getDisplay();
		static::assertStringContainsString( 'No job found', $output );
		static::assertSame( 1, $this->commandTester->getStatusCode() );

		$this->commandTester->execute( [ '--id' => $job->getId() ] );
		static::assertTrue( $job->isBusy() );
		$output = $this->commandTester->getDisplay();
		static::assertStringContainsString( 'Event statistics successfully saved', $output );
		static::assertSame( 0, $this->commandTester->getStatusCode() );
	}
}
