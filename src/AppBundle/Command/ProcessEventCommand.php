<?php
/**
 * This file contains only the ProcessEventCommand class.
 */

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Psr\Container\ContainerInterface;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Repository\EventRepository;
use DateTime;

/**
 * The ProcessEventCommand handles the core logic of calculating statistics for an event.
 */
class ProcessEventCommand extends Command
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var OutputInterface The output of the process. */
    private $output;

    /** @var \Doctrine\ORM\EntityManager The Doctrine EntityManager. */
    protected $entityManager;

    /** @var Event The event we're generating stats for. */
    protected $event;

    /** @var EventRepository The repository for the Event. */
    protected $eventRepo;

    /**
     * Constructor for the ProcessEventCommand.
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
        $this->output = $output;

        $eventId = $input->getArgument('eventId');
        $this->eventRepo = $this->entityManager->getRepository('Model:Event');
        $this->eventRepo->setContainer($this->container);
        $this->event = $this->eventRepo->findOneBy(['id' => $eventId]);

        if ($this->event === null) {
            $this->output->writeln("<error>Event with ID $eventId not found.</error>");
            return 1;
        }

        $this->output->writeln([
            "\nEvent processor",
            '===============',
            '',
        ]);

        $this->output->writeln('Event title: '.$this->event->getTitle());
        $this->output->writeln('Event ID: '.$eventId);

        // Generate and persist each type of EventStat.
        $this->setNewEditors();

        // Save the EventStat's to the database.
        $this->flush();
    }

    /**
     * Compute and persist a new EventStat for the number of new editors.
     */
    private function setNewEditors()
    {
        $this->output->writeln("\nFetching number of new editors...");
        $numNewEditors = $this->eventRepo->getNumNewEditors($this->event);
        $this->createOrUpdateEventStat('new-editors', $numNewEditors);
        $this->output->writeln(">> <info>New editors: $numNewEditors</info>");
    }

    /**
     * Persist an EventStat with given metric and value, or update the
     * existing one, if present.
     * @param  string $metric
     * @param  mixed $value
     * @return EventStat
     */
    private function createOrUpdateEventStat($metric, $value)
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => $metric,
            ]);

        if ($eventStat === null) {
            $eventStat = new EventStat($this->event, $metric, $value);
        } else {
            $eventStat->setValue($value);
        }

        $this->entityManager->persist($eventStat);

        return $eventStat;
    }

    /**
     * Save the persisted EventStat's to the database.
     */
    private function flush()
    {
        // Update the 'updated' attribute.
        $this->event->setUpdated(new DateTime());
        $this->entityManager->persist($this->event);

        $this->entityManager->flush();
        $this->output->writeln("\n<info>Event statistics successfully saved.</info>\n");
    }
}
