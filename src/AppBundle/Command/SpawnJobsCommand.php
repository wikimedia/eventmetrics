<?php
/**
 * This file contains only the SpawnJobsCommand class.
 */

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Container\ContainerInterface;
use AppBundle\Model\Job;
use DateTime;

/**
 * The SpawnJobsCommand will query the jobs table and run the ProcessEventCommand
 * for any job that hasn't started. This is ran via a cron, but can also
 * be called manually via the console with `php bin/console app:process-events`.
 */
class SpawnJobsCommand extends Command
{
    // Max number of open connections allowed. We intentionally
    // set this lower to allow for wiggle room for queries in the main
    // application, unrelated to processing jobs.
    const DATABASE_QUOTA = 5;

    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var OutputInterface The output of the process. */
    private $output;

    /** @var \Doctrine\ORM\EntityManager The Doctrine EntityManager. */
    protected $entityManager;

    /**
     * Constructor for SpawnJobsCommand.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->entityManager = $container->get('doctrine')->getManager();

        parent::__construct();
    }

    /**
     * Configuration for the Symfony console command.
     */
    protected function configure()
    {
        $this->setName('app:spawn-jobs')
            ->setDescription('Spawn jobs to process all events that are in the queue.')
            ->addOption(
                'dry',
                null,
                InputOption::VALUE_NONE,
                'Manipulate job queue without actually spawning jobs'
            );
    }

    /**
     * Called when the command is executed.
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->output->writeln([
            "\nJob queue processor",
            '===================',
            '',
        ]);

        $jobs = $this->getQueuedJobs();
        $numJobs = count($jobs);

        if ($numJobs === 0) {
            return $this->output->writeln(
                "<comment>No jobs found in the queue.</comment>\n"
            );
        }

        $this->output->writeln("\nSpawning $numJobs unstarted job(s)...");

        $this->processJobs($jobs, $input->getOption('dry'));

        $this->entityManager->flush();
        $this->output->writeln("\n<info>$numJobs job(s) started successfully.</info>\n");
    }

    /**
     * Start a process for each given Job.
     * Each Job will also be flagged as having been started.
     * @param Job[] $jobs
     * @param bool $dryRun If set, process won't actually be started.
     */
    private function processJobs($jobs, $dryRun = false)
    {
        foreach ($jobs as $job) {
            // Get the associated event ID.
            $eventId = $job->getEvent()->getId();

            /**
             * @codeCoverageIgnore
             */
            if ($dryRun === false) {
                // Run a new process for the event asynchronously.
                $process = new Process("php bin/console app:process-event $eventId");
                $process->start();
            }

            // Flag the job as started.
            $job->setStarted();
        }
    }

    /**
     * Get any unstarted Jobs, returning no more than the difference of
     * self::DATABASE_QUOTA and the current number of open connections.
     * @return Job[]
     */
    private function getQueuedJobs()
    {
        /** @var int Number of jobs to fire. This shouldn't be a negative number :) */
        $limit = max([self::DATABASE_QUOTA - $this->getNumOpenConnections(), 0]);

        return $this->entityManager
            ->getRepository('Model:Job')
            ->findBy(['started' => false], [], $limit);
    }

    /**
     * Get the number of open connections to the replicas database.
     * @return int
     */
    private function getNumOpenConnections()
    {
        $conn = $this->container
            ->get('doctrine')
            ->getManager('replicas')
            ->getConnection();

        return (int)$conn->query(
            'SELECT COUNT(*) FROM information_schema.PROCESSLIST'
        )->fetchColumn(0);
    }
}
