<?php
/**
 * This file contains only the JobHandler class.
 */

namespace AppBundle\Service;

use AppBundle\Model\Job;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A JobHandler spawns new jobs from the queue if there is quota.
 * An individual Job handles generating statistics for events.
 */
class JobHandler
{
    // Max number of open connections allowed. We intentionally
    // set this lower to allow for wiggle room for queries in the main
    // application, unrelated to processing jobs.
    const DATABASE_QUOTA = 5;

    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var LoggerInterface The logger. */
    private $logger;

    /** @var OutputInterface The output stream, used when calling from a Command. */
    private $output;

    /** @var \Doctrine\ORM\EntityManager The Doctrine EntityManager. */
    private $entityManager;

    /** @var EventProcessor Handles processing of a single event (job). */
    private $eventProcessor;

    /**
     * Constructor for the EventProcessor.
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     * @param EventProcessor $eventProcessor
     */
    public function __construct(
        LoggerInterface $logger,
        ContainerInterface $container,
        EventProcessor $eventProcessor
    ) {
        $this->logger = $logger;
        $this->container = $container;
        $this->eventProcessor = $eventProcessor;
        $this->entityManager = $container->get('doctrine')->getManager();
    }

    /**
     * Query the job queue and spawn Jobs, attempting no more than what
     * is permitted with our quota.
     * @param OutputInterface &$output Used by Commands so that the output
     *   can be controlled by the parent process. If this is null,
     *   a local LoggerInterface is used instead.
     * @return int The number of jobs processed.
     */
    public function spawnAll(OutputInterface &$output = null)
    {
        $this->output = $output;

        /**
         * We can't stub the number of open connections, without
         * stubbing all database interaction with a Repository.
         * @codeCoverageIgnoreStart
         */
        if ($this->getQuota() === 0) {
            $this->log(
                "<error>Not enough datbase quota to run any jobs. Please try again later.</error>"
            );
        }
        // @codeCoverageIgnoreEnd

        $jobs = $this->getQueuedJobs();
        $numJobs = count($jobs);

        if ($numJobs === 0) {
            $this->log("<comment>No jobs found in the queue.</comment>\n");
            return 0;
        }

        $this->log("\nSpawning $numJobs unstarted job(s)...");

        foreach ($jobs as $job) {
            $this->processJob($job);
        }

        $this->log("\n<info>$numJobs job(s) successfully completed.</info>\n");

        return $numJobs;
    }

    /**
     * Spawn the given Job, but only if there is quota.
     * @param Job $job
     * @param OutputInterface &$output Used by Commands so that the output
     *   can be controlled by the parent process. If this is null,
     *   a local LoggerInterface is used instead.
     * @return array|false The generated stats, or false if there's no quota.
     */
    public function spawn(Job $job, OutputInterface &$output = null)
    {
        $this->output = $output;

        /**
         * We can't stub the number of open connections, without
         * stubbing all database interaction with a Repository.
         * @codeCoverageIgnoreStart
         */
        if ($this->getQuota() === 0) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return $this->processJob($job);
    }

    /**
     * Start a process for a single job.
     * @param Job $job
     * @return array|null Generated stats, or null if queued or failed.
     * @codeCoverageIgnore
     */
    private function processJob(Job $job)
    {
        // Flag the job as started. This must be flushed to the database
        // immediately to avoid conflicts with the cron job, and to ensure
        // the flag is set at the beginning of processing.
        $job->setStarted();
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        // Process the Event the Job is associated with.
        return $this->eventProcessor->process($job->getEvent(), $this->output);
    }

    /**
     * Get any unstarted Jobs, returning no more than the difference of
     * self::DATABASE_QUOTA and the current number of open connections.
     * @return Job[]
     */
    private function getQueuedJobs()
    {
        /** @var int Number of jobs to fire. This shouldn't be a negative number :) */
        $limit = $this->getQuota();

        return $this->entityManager
            ->getRepository('Model:Job')
            ->findBy(['started' => false], [], $limit);
    }

    /**
     * Get the number of jobs we can run concurrently, based on
     * how many queries are already running and our quota.
     * @return int
     */
    private function getQuota()
    {
        return max([self::DATABASE_QUOTA - $this->getNumOpenConnections(), 0]);
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

    /**
     * Log a message using the LoggerInterface or OutputInterface,
     * the latter being used when running EventProcessor from a Command.
     * @param string $message
     *
     * This is simple logging. The LoggerInterface portion cannot easily
     * be tested, but the output via $this->output does have test coverage.
     * @codeCoverageIgnore
     */
    private function log($message)
    {
        if ($this->output === null) {
            $this->logger->info($message);
        } else {
            $this->output->writeln($message);
        }
    }
}
