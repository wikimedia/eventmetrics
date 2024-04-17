<?php declare( strict_types=1 );

namespace App\Command;

use App\Model\Job;
use App\Repository\JobRepository;
use App\Service\JobHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The SpawnJobsCommand will query the jobs table and run the JobHandler
 * for any job that hasn't started. This is ran via a cron, but can also
 * be called manually via the console with `php bin/console app:spawn-jobs`.
 */
class SpawnJobsCommand extends Command {

	/**
	 * Constructor for SpawnJobsCommand.
	 * @param JobHandler $jobHandler
	 * @param JobRepository $jobRepo
	 */
	public function __construct(
		private readonly JobHandler $jobHandler,
		private readonly JobRepository $jobRepo
	) {
		parent::__construct();
	}

	/**
	 * Configuration for the Symfony console command.
	 */
	protected function configure(): void {
		$this->setName( 'app:spawn-jobs' )
			->setDescription(
				'Spawn jobs to process all events that are in the queue, respecting database quota.'
			)
			->addOption(
				'id',
				null,
				InputOption::VALUE_REQUIRED,
				'Spawn only the job with the given ID, if there is enough quota.'
			);
	}

	/**
	 * Called when the command is executed.
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int Exit code.
	 */
	public function execute( InputInterface $input, OutputInterface $output ): int {
		$output->writeln( [
			"\nJob queue processor",
			'===================',
			'',
		] );

		$jobId = $input->getOption( 'id' );

		if ( !$jobId ) {
			$this->jobHandler->spawnAll( $output );
			return 0;
		}

		/** @var Job|null $job */
		$job = $this->jobRepo->findOneBy( [ 'id' => (int)$jobId ] );

		if ( $job === null ) {
			$output->writeln( "<error>No job found with ID $jobId</error>" );
			return 1;
		}

		$this->jobHandler->spawn( $job, $output );
		return 0;
	}
}
