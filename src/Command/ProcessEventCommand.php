<?php declare( strict_types=1 );

namespace App\Command;

use App\Model\Event;
use App\Repository\EventRepository;
use App\Service\EventProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The ProcessEventCommand handles the core logic of calculating statistics for an event.
 */
class ProcessEventCommand extends Command {

	/**
	 * Constructor for the ProcessEventCommand.
	 * @param EventRepository $eventRepo
	 * @param EventProcessor $eventProcessor
	 */
	public function __construct(
		private readonly EventRepository $eventRepo,
		private readonly EventProcessor $eventProcessor
	) {
		parent::__construct();
	}

	/**
	 * Configuration for the Symfony console command.
	 */
	protected function configure(): void {
		$this->setName( 'app:process-event' )
			->setDescription( 'Generates statistics for the given event.' )
			->addArgument( 'eventId', InputArgument::REQUIRED, 'The ID of the event' );
	}

	/**
	 * Called when the command is executed.
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int Exit code.
	 */
	public function execute( InputInterface $input, OutputInterface $output ): int {
		$eventId = $input->getArgument( 'eventId' );

		/** @var Event $event */
		$event = $this->eventRepo->findOneBy( [ 'id' => $eventId ] );

		if ( $event === null ) {
			$output->writeln( "<error>Event with ID $eventId not found.</error>" );
			return 1;
		}

		$output->writeln( [
			"\nEvent processor",
			'===============',
			'',
		] );

		$this->eventProcessor->process( $event, $output );
		return 0;
	}
}
