<?php declare( strict_types=1 );

namespace App\Command;

use App\Model\Event;
use App\Model\Job;
use App\Repository\EventRepository;
use App\Service\JobHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The ProcessAllEventsCommand regenerates stats for all events.
 * Use only when breaking changes have been introduced that require all events be updated.
 */
class ProcessAllEventsCommand extends Command {

	/**
	 * Constructor for the ProcessEventCommand.
	 * @param JobHandler $jobHandler
	 * @param EntityManagerInterface $entityManager
	 * @param EventRepository $eventRepo
	 */
	public function __construct(
		private readonly JobHandler $jobHandler,
		private readonly EntityManagerInterface $entityManager,
		private readonly EventRepository $eventRepo
	) {
		parent::__construct();
	}

	/**
	 * Configuration for the Symfony console command.
	 */
	protected function configure(): void {
		$this->setName( 'app:process-all-events' )
			->setDescription( 'Creates jobs to update data for every event.' )
			->addOption(
				'no-spawn',
				's',
				InputOption::VALUE_NONE,
				"Don't attempt to spawn jobs immediately, instead letting the cron handle them"
			);
	}

	/**
	 * Called when the command is executed.
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int Exit code.
	 */
	public function execute( InputInterface $input, OutputInterface $output ): int {
		/** @var Event[] $events */
		$events = $this->eventRepo->findAll();

		foreach ( $events as $event ) {
			// Only create Jobs for Events that have all the necessary settings.
			if ( $event->isValid() && $event->hasJob() === false ) {
				$job = new Job( $event );
				$this->entityManager->persist( $job );
			}
		}

		$this->entityManager->flush();

		if ( $input->getOption( 'no-spawn' ) === false ) {
			// Spawn all Jobs, attempting no more than what is permitted with our quota.
			// Remaining Jobs will be queued up and spawned via the cron.
			$this->jobHandler->spawnAll( $output );
		}

		return 0;
	}
}
