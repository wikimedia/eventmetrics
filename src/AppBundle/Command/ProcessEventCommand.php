<?php
/**
 * This file contains only the ProcessEventCommand class.
 */

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Container\ContainerInterface;
use AppBundle\Service\EventProcessor;

/**
 * The ProcessEventCommand handles the core logic of calculating statistics for an event.
 */
class ProcessEventCommand extends Command
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var OutputInterface The output of the process. */
    private $output;

    /** @var EventProcessor Handles processing of a single event. */
    private $eventProcessor;

    /**
     * Constructor for the ProcessEventCommand.
     * @param ContainerInterface $container
     * @param EventProcessor $eventProcessor
     */
    public function __construct(
        ContainerInterface $container,
        EventProcessor $eventProcessor
    ) {
        $this->container = $container;
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->eventProcessor = $eventProcessor;

        parent::__construct();
    }

    /**
     * Configuration for the Symfony console command.
     */
    protected function configure()
    {
        $this->setName('app:process-event')
            ->setDescription('Generates statistics for the given event.')
            ->addArgument('eventId', InputArgument::REQUIRED, 'The ID of the event');
    }

    /**
     * Called when the command is executed.
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $eventId = $input->getArgument('eventId');
        $eventRepo = $this->entityManager->getRepository('Model:Event');
        $event = $eventRepo->findOneBy(['id' => $eventId]);

        if ($event === null) {
            $output->writeln("<error>Event with ID $eventId not found.</error>");
            return 1;
        }

        $output->writeln([
            "\nEvent processor",
            '===============',
            '',
        ]);

        $this->eventProcessor->process($event, $output);
    }
}
