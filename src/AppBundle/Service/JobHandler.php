<?php
/**
 * This file contains only the JobHandler class.
 */

declare(strict_types=1);

namespace AppBundle\Service;

use AppBundle\Model\Event;
use AppBundle\Model\Job;
use DateTime;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

/**
 * A JobHandler spawns new jobs from the queue if there is quota.
 * An individual Job handles generating statistics for an Event.
 */
class JobHandler
{
    // Max number of open connections allowed. We intentionally set this lower to allow for wiggle room for
    // queries in the main application, unrelated to processing jobs.
    private const DATABASE_QUOTA = 5;

    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var ReplicasClient */
    private $replicasClient;

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
        ReplicasClient $replicasClient,
        EventProcessor $eventProcessor
    ) {
        $this->logger = $logger;
        $this->container = $container;
        $this->replicasClient = $replicasClient;
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
     * @throws \Exception
     */
    public function spawn(Job $job, ?OutputInterface &$output = null): void
    {
        $this->output = $output;

        // We can't stub the number of open connections, without stubbing all database interaction with a Repository.
        // @codeCoverageIgnoreStart
        if (0 === $this->getQuota()) {
            throw new \Exception('Database quota exceeded!');
        }
        // @codeCoverageIgnoreEnd

        $this->processJob($job);
    }

    /**
     * Check for old jobs that never started or are mysteriously running for a very long time, and mark them as timed
     * out. Later, kill such jobs unless evenshow.js haven't done it for us upon showing an error message.
     * This does NOT kill the process associated with the job, if there is one. Called in EventController::showAction.
     * @param Event $event
     */
    public function handleStaleJobs(Event $event): void
    {
        $staleJobs = $event->getStaleJobs();

        if ($staleJobs->isEmpty()) {
            return;
        }

        $dayAgo = new DateTime('-1 day');
        /** @var Job $job */
        foreach ($staleJobs->getIterator() as $job) {
            if ($job->isBusy()) {
                $job->setStatus(Job::STATUS_FAILED_TIMEOUT);
            }
            if ($job->getSubmitted() >= $dayAgo) {
                $event->removeJob($job);
            }
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
     * @throws \Exception
     */
    private function processJob(Job $job): void
    {
        // Flag the job as started. This must be flushed to the database immediately to avoid conflicts with the
        // cron job, and to ensure the flag is set at the beginning of processing.
        $job->setStatus(Job::STATUS_STARTED);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        try {
            // Process the Event the Job is associated with.
            $this->eventProcessor->process($job->getEvent(), $this->output);
        } catch (\Throwable $e) {
            $eventId = $job->getEvent()->getId();

            // Doctrine DriverExceptions are handled in Repository::handleDriverError().
            // This code checks the exceptions that methods throws.
            if ('error-query-timeout' === $e->getMessage()) {
                $job->setStatus(Job::STATUS_FAILED_TIMEOUT);
                $errorMessage = "Job for event $eventId timed out";
            } else {
                $job->setStatus(Job::STATUS_FAILED_UNKNOWN);
                $errorMessage = "Job for event $eventId failed";
            }
            $this->entityManager->persist($job);
            $this->entityManager->flush();

            // The client will make requests to get the status of the Job and act accordingly.
            // We still want to throw the exception so we can get notified by email.
            throw new \Exception($errorMessage, 0, $e);
        }
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
            ->findBy(['status' => Job::STATUS_QUEUED], [], $limit);
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
     * Get the number of open connections to the replicas databases.
     * @return int
     */
    private function getNumOpenConnections(): int
    {
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $this->container->get('doctrine');
        $maxProcessCount = 0;
        for ($slice = 1; $slice < 9; $slice++) {
            $conn = $registry->getConnection('toolforge_s'.$slice);
            $processCount = (int)$conn->executeQuery(
                'SELECT COUNT(*) FROM information_schema.PROCESSLIST'
            )->fetchFirstColumn();
            $maxProcessCount = max($processCount, $maxProcessCount);
        }
        return $maxProcessCount;
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
