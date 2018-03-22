<?php
/**
 * This file contains only the EventProcessor class.
 */

namespace AppBundle\Service;

use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\EventWikiStat;
use AppBundle\Repository\EventRepository;
use DateTime;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An EventProcessor handles generating statistics for an Event.
 */
class EventProcessor
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var LoggerInterface The logger. */
    private $logger;

    /** @var \Doctrine\ORM\EntityManager The Doctrine EntityManager. */
    private $entityManager;

    /** @var Event The event we're generating stats for. */
    private $event;

    /** @var EventRepository The repository for the Event. */
    private $eventRepo;

    /** @var OutputInterface The output stream, used when calling from a Command. */
    private $output;

    /** @var string[]|null Usernames of the new editors. */
    private $newEditors;

    /** @var array The generated stats, keyed by metric. */
    protected $stats;

    /**
     * Constructor for the EventProcessor.
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     */
    public function __construct(LoggerInterface $logger, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->entityManager = $container->get('doctrine')->getManager();
    }

    /**
     * Generate and store statistics for the given event.
     * @param Event $event
     * @param OutputInterface|null &$output Used by Commands so that the output
     *   can be controlled by the parent process. If this is null,
     *   a local LoggerInterface is used instead.
     * @return array Statistics, keyed by metric, along with 'wikis' which
     *   is a similar array of statistics, but keyed by the wiki's domain.
     */
    public function process(Event $event, OutputInterface &$output = null)
    {
        $this->loadEvent($event);

        $this->output = &$output;

        $this->log('Processing event '.$event->getId());

        // Generate and persist each type of EventStat/EventWikiStat.
        $this->setNewEditors();
        $this->setPagesEdited();
        $this->setRetention();

        // Clear out any existing job records from the queue.
        $event->removeJobs();

        // Update the 'updated' attribute.
        $event->setUpdated(new DateTime());
        $this->entityManager->persist($event);

        // Flush to the database.
        $this->entityManager->flush();

        $this->log("\n<info>Event statistics successfully saved.</info>\n");

        return $this->stats;
    }

    /**
     * Load the Event and EventRepository.
     * @param Event $event
     */
    private function loadEvent(Event $event)
    {
        $this->event = $event;
        $this->eventRepo = $this->entityManager->getRepository('Model:Event');
        $this->eventRepo->setContainer($this->container);
    }

    /**
     * Compute and persist a new EventStat for the number of new editors.
     */
    private function setNewEditors()
    {
        $this->log("\nFetching number of new editors...");
        $numNewEditors = count($this->getNewEditors());
        $newEditorOffset = Event::getAvailableMetrics()['new-editors'];
        $this->createOrUpdateEventStat('new-editors', $numNewEditors, $newEditorOffset);
        $this->log(">> <info>New editors: $numNewEditors</info>");
    }

    /**
     * Get the usernames of the new editors.
     * @return string[]
     */
    private function getNewEditors()
    {
        if (!is_array($this->newEditors)) {
            $this->newEditors = $this->eventRepo->getNewEditors($this->event);
        }
        return $this->newEditors;
    }

    /**
     * Compute and persist a new EventStat and EventWikiStats
     * for the number of pages created/improved.
     */
    private function setPagesEdited()
    {
        $this->log("\nFetching number of pages created...");

        $start = $this->event->getStartWithTimezone();
        $end = $this->event->getEndWithTimezone();
        $usernames = $this->getParticipantNames();

        $pagesImproved = 0;
        $pagesCreated = 0;

        $ewRepo = $this->entityManager->getRepository('Model:EventWiki');
        $ewRepo->setContainer($this->container);

        foreach ($this->event->getWikis() as $wiki) {
            $dbName = $ewRepo->getDbName($wiki);
            $ret = $this->eventRepo->getNumPagesEdited(
                $dbName,
                $start,
                $end,
                $usernames
            );
            $pagesImproved += $ret['edited'];
            $pagesCreated += $ret['created'];

            $this->createOrUpdateEventWikiStat($wiki, 'pages-created', $ret['created']);
            $this->createOrUpdateEventWikiStat($wiki, 'pages-improved', $ret['edited']);
        }

        $this->createOrUpdateEventStat('pages-created', $pagesCreated);
        $this->createOrUpdateEventStat('pages-improved', $pagesImproved);

        $this->log(">> <info>Pages created: $pagesCreated</info>");
        $this->log(">> <info>Pages improved: $pagesImproved</info>");
    }

    /**
     * Get the usernames of the participants of the Event.
     * @return string[]
     */
    private function getParticipantNames()
    {
        $userIds = $this->event->getParticipantIds();
        return array_column(
            $this->eventRepo->getUsernamesFromIds($userIds),
            'user_name'
        );
    }

    /**
     * Compute and persist the number of users who met the retention threshold.
     */
    private function setRetention()
    {
        $this->log("\nFetching retention...");

        $retentionOffset = Event::getAvailableMetrics()['retention'];
        $end = $this->event->getEndWithTimezone()->modify("+$retentionOffset days");

        // Only calculate for new editors.
        $usernames = $this->getNewEditors();

        if ((new DateTime()) < $end) {
            $numUsersRetained = count($usernames);
        } else {
            // First grab the list of common wikis edited amongst all users,
            // so we don't unnecessarily query all wikis.
            $dbNames = $this->eventRepo->getCommonWikis($usernames);
            sort($dbNames);

            $numUsersRetained = $this->getNumUsersRetained($dbNames, $end, $usernames);
        }

        $this->createOrUpdateEventStat('retention', $numUsersRetained, $retentionOffset);

        $this->log(">> <info>Number of users retained: $numUsersRetained</info>");
    }

    /**
     * Loop through the wikis and get the number of users retained.
     * @param  string[] $dbNames Database names of the wikis to loop through.
     * @param  DateTime $end Search from this day onward.
     * @param  string[] $usernames Usernames to search for.
     * @return int
     */
    private function getNumUsersRetained($dbNames, $end, $usernames)
    {
        // Create and display progress bar for looping through wikis.
        if ($this->output !== null) {
            $progress = new ProgressBar($this->output, count($dbNames));
            $progress->setFormatDefinition(
                'custom',
                " <comment>%message%</comment>\n".
                " %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %memory:6s%\n"
            );
            $progress->setFormat('custom');
            $progress->start();
        }

        $usersRetained = $this->getNumUsersRetainedInner($dbNames, $usernames, $end, $progress);

        // Clear out progress bar if we're running this from a Command.
        if ($this->output !== null) {
            $progress->setMessage('');
            $progress->finish();
        }

        if ($usersRetained === true) {
            $this->log(
                " <comment>Short-circuiting, all users have met retention threshold.</comment>\n"
            );
            return count($usernames);
        }

        return $usersRetained;
    }

    /**
     * Loop through the given databases and determine how many of the given
     * users have made at least one edit after the given date. If all users have
     * met the retention threshold, the looping short-circuits and `true` is returned.
     * @param  string[] $dbNames
     * @param  string[] $usernames [description]
     * @param  DateTime $end
     * @param  ProgressBar &$progress The progress bar, used when running from a Command.
     * @return int Number of users retained.
     */
    private function getNumUsersRetainedInner($dbNames, $usernames, $end, &$progress)
    {
        $usersRetained = [];

        foreach ($dbNames as $dbName) {
            if ($this->output !== null) {
                $progress->setMessage($dbName);
                $progress->advance();
            }

            $ret = $this->eventRepo->getUsersRetained($dbName, $end, $usernames);
            $usersRetained = array_unique(array_merge($ret, $usersRetained));

            // Short-circuit if we've confirmed that all users have met the retention threshold.
            if (count($usersRetained) === count($usernames)) {
                return true;
            }
        }

        return count($usersRetained);
    }

    /**
     * Persist an EventStat with given metric and value, or update the
     * existing one, if present.
     * @param  string $metric
     * @param  mixed $value
     * @param  int $offset Offset value associated with the metric,
     *   such as the number of days in evaluating retention.
     * @return EventStat
     */
    private function createOrUpdateEventStat($metric, $value, $offset = null)
    {
        // Update class property.
        $this->stats[$metric] = [
            'value' => $value,
            'offset' => $offset
        ];

        // Create or update an EventStat.
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => $metric,
            ]);

        if ($eventStat === null) {
            $eventStat = new EventStat($this->event, $metric, $value, $offset);
        } else {
            $eventStat->setValue($value);
        }

        $this->entityManager->persist($eventStat);

        return $eventStat;
    }

    /**
     * Persist an EventWikiStat with given metric and value, or update the
     * existing one, if present.
     * @param EventWikiStat $wiki
     * @param string $metric
     * @param mixed $value
     * @param int $offset Offset value associated with the metric,
     *   such as the number of days in evaluating retention.
     * @return EventWikiStat
     */
    private function createOrUpdateEventWikiStat(EventWiki $wiki, $metric, $value, $offset = null)
    {
        $domain = $wiki->getDomain();

        // Update class property.
        if (!isset($this->stats['wikis'][$domain])) {
            $this->stats['wikis'][$domain] = [];
        }
        $this->stats['wikis'][$domain][$metric] = [
            'value' => $value,
            'offset' => $offset
        ];

        // Create or update an EventStat.
        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $wiki,
                'metric' => $metric,
            ]);

        if ($eventWikiStat === null) {
            $eventWikiStat = new EventWikiStat($wiki, $metric, $value, $offset);
        } else {
            $eventWikiStat->setValue($value);
        }

        $this->entityManager->persist($eventWikiStat);

        return $eventWikiStat;
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
