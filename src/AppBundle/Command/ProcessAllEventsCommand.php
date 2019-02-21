<?php

declare(strict_types=1);

namespace AppBundle\Command;

use AppBundle\Model\Event;
use AppBundle\Model\Job;
use AppBundle\Service\JobHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ProcessAllEventsCommand regenerates stats for all events.
 * Use only when breaking changes have been introduced that require all events be updated.
 */
class ProcessAllEventsCommand extends Command
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var \Doctrine\ORM\EntityManager The Doctrine EntityManager. */
    private $entityManager;

    /** @var JobHandler Handles the job queue system. */
    private $jobHandler;

    /**
     * Constructor for the ProcessEventCommand.
     * @param ContainerInterface $container
     * @param JobHandler $jobHandler
     */
    public function __construct(ContainerInterface $container, JobHandler $jobHandler)
    {
        $this->container = $container;
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->jobHandler = $jobHandler;

        parent::__construct();
    }

    /**
     * Configuration for the Symfony console command.
     */
    protected function configure(): void
    {
        $this->setName('app:process-all-events')
            ->setDescription('Creates jobs to update data for every event.')
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
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Event[] $events */
        $events = $this->entityManager
            ->getRepository('Model:Event')
            ->findAll();

        foreach ($events as $event) {
            // Only create Jobs for Events that have all the necessary settings.
            if ($event->isValid() && false === $event->hasJob()) {
                $job = new Job($event);
                $this->entityManager->persist($job);
            }
        }

        $this->entityManager->flush();

        if (false === $input->getOption('no-spawn')) {
            // Spawn all Jobs, attempting no more than what is permitted with our quota.
            // Remaining Jobs will be queued up and spawned via the cron.
            $this->jobHandler->spawnAll($output);
        }

        return 0;
    }
}
