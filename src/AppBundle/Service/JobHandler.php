<?php
/**
 * This file contains only the JobHandler class.
 */

declare(strict_types=1);

namespace AppBundle\Service;

use AppBundle\Model\Event;
use AppBundle\Model\Job;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A JobHandler spawns new jobs from the queue if there is quota.
 * An individual Job handles generating statistics for an Event.
 */
class JobHandler
{
    // Max number of open connections allowed. We intentionally
    // set this lower to allow for wiggle room for queries in the main
    // application, unrelated to processing jobs.
    private const DATABASE_QUOTA = 5;

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
     * Query the job queue and spawn Jobs, attempting no more than what is permitted with our quota.
     * @param OutputInterface &$output Used by Commands so that the output can be controlled by the parent process.
     *   If this is null, a local LoggerInterface is used instead.
     * @return int The number of jobs processed.
     */
    public function spawnAll(?OutputInterface &$output = null): int
    {
        $this->output = $output;

        /**
         * We can't stub the number of open connections, without
         * stubbing all database interaction with a Repository.
         * @codeCoverageIgnoreStart
         */
        if (0 === $this->getQuota()) {
            $this->log(
                "<error>Not enough database quota to run any jobs. Please try again later.</error>"
            );
        }
        // @codeCoverageIgnoreEnd

        $jobs = $this->getQueuedJobs();
        $numJobs = count($jobs);

        if (0 === $numJobs) {
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
     * @param OutputInterface &$output Used by Commands so that the output can be controlled by the parent process.
     *   If this is null, a local LoggerInterface is used instead.
     */
    public function spawn(Job $job, ?OutputInterface &$output = null): void
    {
        $this->output = $output;

        /**
         * We can't stub the number of open connections, without stubbing all database interaction with a Repository.
         * @codeCoverageIgnoreStart
         */
        if (0 === $this->getQuota()) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $this->processJob($job);
    }

    /**
     * Check for old jobs that never started or are mysteriously running for a very long time, and lay them to rest.
     * This does NOT kill the process associated with the job, if there is one. Called in EventController::showAction.
     * @param Event $event
     */
    public function handleStaleJobs(Event $event): void
    {
        $staleJobs = $event->getStaleJobs();

        if ($staleJobs->isEmpty()) {
            return;
        }

        /** @var Job $job */
        foreach ($staleJobs->getIterator() as $job) {
            $event->removeJob($job);
        }

        // This needs to be flushed immediately because this method is called in EventController::showAction,
        // and the event page itself indicates whether there are pending/currently running jobs.
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    /**
     * Start a process for a single job.
     * @param Job $job
     * @codeCoverageIgnore
     */
    private function processJob(Job $job): void
    {
        // Flag the job as started. This must be flushed to the database
        // immediately to avoid conflicts with the cron job, and to ensure
        // the flag is set at the beginning of processing.
        $job->setStarted();
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        // Process the Event the Job is associated with.
        $this->eventProcessor->process($job->getEvent(), $this->output);
    }

    /**
     * Get any unstarted Jobs, returning no more than the difference of
     * self::DATABASE_QUOTA and the current number of open connections.
     * @return Job[]
     */
    private function getQueuedJobs(): array
    {
        /** @var int $limit Number of jobs to fire. This shouldn't be a negative number :) */
        $limit = $this->getQuota();

        return $this->entityManager
            ->getRepository('Model:Job')
            ->findBy(['started' => false], [], $limit);
    }

    /**
     * Get the number of jobs we can run concurrently, based on how many queries are already running and our quota.
     * @return int
     */
    private function getQuota(): int
    {
        return max([self::DATABASE_QUOTA - $this->getNumOpenConnections(), 0]);
    }

    /**
     * Get the number of open connections to the replicas database.
     * @return int
     */
    private function getNumOpenConnections(): int
    {
        /** @var Connection $conn */
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
    private function log(string $message): void
    {
        if (null === $this->output) {
            $this->logger->info($message);
        } else {
            $this->output->writeln($message);
        }
    }
}
