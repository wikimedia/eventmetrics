<?php
/**
 * This file contains only the SpawnJobsCommand class.
 */

declare(strict_types=1);

namespace AppBundle\Command;

use AppBundle\Model\Job;
use AppBundle\Service\JobHandler;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The SpawnJobsCommand will query the jobs table and run the JobHandler
 * for any job that hasn't started. This is ran via a cron, but can also
 * be called manually via the console with `php bin/console app:spawn-jobs`.
 */
class SpawnJobsCommand extends Command
{
    /** @var JobHandler Handles the job queue system. */
    private $jobHandler;

    /** @var \Doctrine\ORM\EntityManager The Doctrine EntityManager. */
    private $entityManager;

    /**
     * Constructor for SpawnJobsCommand.
     * @param ContainerInterface $container
     * @param JobHandler $jobHandler
     */
    public function __construct(ContainerInterface $container, JobHandler $jobHandler)
    {
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->jobHandler = $jobHandler;
        parent::__construct();
    }

    /**
     * Configuration for the Symfony console command.
     */
    protected function configure(): void
    {
        $this->setName('app:spawn-jobs')
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
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int Exit code.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            "\nJob queue processor",
            '===================',
            '',
        ]);

        $jobId = $input->getOption('id');

        if (!$jobId) {
            $this->jobHandler->spawnAll($output);
            return 0;
        }

        /** @var Job|null $job */
        $job = $this->entityManager
            ->getRepository('Model:Job')
            ->findOneBy(['id' => (int)$jobId]);

        if (null === $job) {
            $output->writeln("<error>No job found with ID $jobId</error>");
            return 1;
        }

        $this->jobHandler->spawn($job, $output);
        return 0;
    }
}
