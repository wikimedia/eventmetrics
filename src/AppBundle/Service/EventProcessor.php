<?php
/**
 * This file contains only the EventProcessor class.
 */

declare(strict_types=1);

namespace AppBundle\Service;

use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\EventWikiStat;
use AppBundle\Repository\EventRepository;
use AppBundle\Repository\EventWikiRepository;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

    /** @var mixed[] The generated stats, keyed by metric. */
    protected $stats;

    /** @var string[] Unique wikis where participants have made edits. */
    protected $commonWikis;

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
     * @param OutputInterface|null &$output Used by Commands so that the output can be controlled by the parent process.
     *   If this is null, a local LoggerInterface is used instead.
     * @return mixed[] Statistics, keyed by metric, along with 'wikis' which
     *   is a similar array of statistics, but keyed by the wiki's domain.
     */
    public function process(Event $event, ?OutputInterface &$output = null): array
    {
        $this->loadEvent($event);

        $this->output = &$output;

        $this->log('Processing event '.$event->getId());

        // Generate and persist each type of EventStat/EventWikiStat.
        $this->setNewEditors();

        // If there is an associated EventWiki that represents a family, we need to first find
        // the common wikis where participants have edited. From there we will create new EventWikis
        // if needed, and later remove those that have no statistics so that the UI is clean.
        $this->createEventWikisFromLang();

        $this->setPagesEdited();

        // Remove EventWikis that are part of a family where there are no statistics.
        $this->removeEventWikisWithNoStats();

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
    private function loadEvent(Event $event): void
    {
        $this->event = $event;
        $this->eventRepo = $this->entityManager->getRepository('Model:Event');
        $this->eventRepo->setContainer($this->container);
    }

    /**
     * Check if there are EventWikis that represent a family (*.wikipedia, *.wiktionary, etc.)
     * and if so, create EventWikis for the ones where participants have made edits.
     */
    private function createEventWikisFromLang(): void
    {
        foreach ($this->event->getFamilyWikis() as $wikiFamily) {
            $domains = $this->eventRepo->getCommonLangWikiDomains(
                $this->getParticipantNames(),
                $wikiFamily->getFamilyName()
            );

            // Find domains of existing EventWikis.
            $existingDomains = array_map(function (EventWiki $eventWiki) {
                return $eventWiki->getDomain();
            }, $this->event->getWikis()->toArray());

            // Create new EventWikis for those that don't exist.
            foreach (array_diff($domains, $existingDomains) as $domain) {
                // The constructor adds the EventWiki to the associated Event.
                // We won't persist these yet, because they will get removed if
                // no statistics exist on that EventWiki.
                new EventWiki($this->event, $domain);
            }
        }
    }

    /**
     * After we've created the EventWikiStats from $this->setPagesEdited(), we want to remove any with zero values
     * that are associated to a family EventWiki. This is because on the event page, we don't want to show zero values
     * for *every* language that is part of a wiki family.
     */
    private function removeEventWikisWithNoStats(): void
    {
        // Find EventWikis that represent a family, but have no stats, and remove them.
        foreach ($this->event->getFamilyWikis() as $wikiFamily) {
            $wikis = $wikiFamily->getChildWikis();

            /** @var EventWiki $wiki */
            foreach ($wikis as $wiki) {
                // Sum all the stats, effectively telling us if there are any stats.
                $statsSum = array_sum($wiki->getStatistics()->map(function (EventWikiStat $stat) {
                    return $stat->getValue();
                })->toArray());

                if (0 === $statsSum) {
                    // $this->process() returns $this->stats, which gets returned in
                    // the JSON response, so we need to remove them here.
                    unset($this->stats['wikis'][$wiki->getDomain()]);

                    // Doctrine wants you to both remove from the Event and from the entity manager.
                    $this->event->removeWiki($wiki);
                    $this->entityManager->remove($wiki);
                }
            }
        }
    }

    /**
     * Compute and persist a new EventStat for the number of new editors.
     */
    private function setNewEditors(): void
    {
        $this->log("\nFetching number of new editors...");
        $numNewEditors = count($this->getNewEditors());
        $newEditorOffset = Event::getAllAvailableMetrics()['new-editors'];
        $this->createOrUpdateEventStat('new-editors', $numNewEditors, $newEditorOffset);
        $this->log(">> <info>New editors: $numNewEditors</info>");
    }

    /**
     * Get the usernames of the new editors.
     * @return string[]
     */
    private function getNewEditors(): array
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
    private function setPagesEdited(): void
    {
        $this->log("\nFetching number of pages created or improved...");

        $pagesImproved = 0;
        $pagesCreated = 0;

        /** @var EventWikiRepository $ewRepo */
        $ewRepo = $this->entityManager->getRepository('Model:EventWiki');
        $ewRepo->setContainer($this->container);

        /** @var bool $pageStats Whether or not EventWikiStats for pages-created or pages-improved are being saved. */
        $pageStats = false;

        foreach ($this->event->getWikis() as $wiki) {
            // No stats for EventWikis that represent a family.
            if ($wiki->isFamilyWiki()) {
                continue;
            }

            // Different stats based on wiki family.
            switch ($wiki->getFamilyName()) {
                case 'wikipedia':
                    $this->setPagesEditedWikipedias($wiki, $ewRepo, $pagesCreated, $pagesImproved);
                    $pageStats = true;
                    break;
                case 'commons':
                    $this->setFilesUploadedCommons($wiki);
                    break;
                case 'wikidata':
                    $this->setItemsCreatedOrImprovedOnWikidata($wiki);
                    break;
            }
        }

        // Only save pages-created and pages-improved as EventStats
        // if they were also saved as EventWikiStats.
        if ($pageStats) {
            $this->createOrUpdateEventStat('pages-created', $pagesCreated);
            $this->createOrUpdateEventStat('pages-improved', $pagesImproved);
        }

        $this->log(">> <info>Pages created: $pagesCreated</info>");
        $this->log(">> <info>Pages improved: $pagesImproved</info>");
    }

    /**
     * Set pages created/improved for the given Wikipedia.
     * @param EventWiki $wiki
     * @param EventWikiRepository $ewRepo
     * @param int &$pagesCreated
     * @param int &$pagesImproved
     */
    private function setPagesEditedWikipedias(
        EventWiki $wiki,
        EventWikiRepository $ewRepo,
        int &$pagesCreated,
        int &$pagesImproved
    ): void {
        $dbName = $ewRepo->getDbNameFromDomain($wiki->getDomain());
        $this->log("> Fetching pages created or improved on {$wiki->getDomain()}...");

        $ret = $this->eventRepo->getNumPagesEdited(
            $dbName,
            $this->event->getStartWithTimezone(),
            $this->event->getEndWithTimezone(),
            $this->getParticipantNames(),
            $this->event->getCategoryIdsForWiki($wiki)
        );
        $pagesCreated += $ret['created'];
        $pagesImproved += $ret['edited'];

        $this->createOrUpdateEventWikiStat($wiki, 'pages-created', $ret['created']);
        $this->createOrUpdateEventWikiStat($wiki, 'pages-improved', $ret['edited']);
    }

    /**
     * Set the number of files uploaded on Commons.
     * @param EventWiki $wiki
     */
    private function setFilesUploadedCommons(EventWiki $wiki): void
    {
        $this->log("> Fetching files uploaded on Commons and global file usage...");

        $start = $this->event->getStartWithTimezone();
        $end = $this->event->getEndWithTimezone();

        $ret = $this->eventRepo->getFilesUploadedCommons($start, $end, $this->getParticipantNames());
        $this->createOrUpdateEventWikiStat($wiki, 'files-uploaded', $ret);
        $this->createOrUpdateEventStat('files-uploaded', $ret);

        $this->log(">> <info>Files uploaded: $ret</info>");

        $ret = $this->eventRepo->getFileUsage($start, $end, $this->getParticipantNames());
        $this->createOrUpdateEventWikiStat($wiki, 'file-usage', $ret);
        $this->createOrUpdateEventStat('file-usage', $ret);

        $this->log(">> <info>Pages where files are used: $ret</info>");
    }

    /**
     * Retrieve counts of Wikidata Items created or improved and save them as both event and event-wiki stats.
     * @param EventWiki $wiki The wiki (must always be Wikidata).
     */
    private function setItemsCreatedOrImprovedOnWikidata(EventWiki $wiki): void
    {
        $this->log("> Fetching items created or improved on Wikidata...");
        // Re-use the same metric calculator as is used for Wikipedia pages above,
        // but store the values under a different name.
        $ret = $this->eventRepo->getNumPagesEdited(
            'wikidatawiki_p',
            $this->event->getStartWithTimezone(),
            $this->event->getEndWithTimezone(),
            $this->getParticipantNames(),
            $this->event->getCategoryIdsForWiki($wiki)
        );
        // Report the counts, and record them both for this wiki and the event (there's only ever one Wikidata wiki).
        $this->log(">> <info>Items created: {$ret['created']}</info>");
        $this->log(">> <info>Items improved: {$ret['edited']}</info>");
        $this->createOrUpdateEventWikiStat($wiki, 'items-created', $ret['created']);
        $this->createOrUpdateEventWikiStat($wiki, 'items-improved', $ret['edited']);
        $this->createOrUpdateEventStat('items-created', $ret['created']);
        $this->createOrUpdateEventStat('items-improved', $ret['edited']);
    }

    /**
     * Get the usernames of the participants of the Event.
     * @return string[]
     */
    private function getParticipantNames(): array
    {
        // Quick cache.
        static $parUsernames = null;
        if (null !== $parUsernames) {
            return $parUsernames;
        }

        $userIds = $this->event->getParticipantIds();
        $parUsernames = array_column(
            $this->eventRepo->getUsernamesFromIds($userIds),
            'user_name'
        );
        return $parUsernames;
    }

    /**
     * Compute and persist the number of users who met the retention threshold.
     */
    private function setRetention(): void
    {
        $this->log("\nFetching retention...");

        $retentionOffset = Event::getAllAvailableMetrics()['retention'];
        $end = $this->event->getEndWithTimezone()->modify("+$retentionOffset days");

        // Only calculate for new editors.
        $usernames = $this->getNewEditors();

        if ((new DateTime()) < $end) {
            $numUsersRetained = count($usernames);
        } else {
            // First grab the list of common wikis edited amongst all users,
            // so we don't unnecessarily query all wikis.
            $dbNames = $this->getCommonWikis($usernames);

            $numUsersRetained = $this->getNumUsersRetained($dbNames, $end, $usernames);
        }

        $this->createOrUpdateEventStat('retention', $numUsersRetained, $retentionOffset);

        $this->log(">> <info>Number of users retained: $numUsersRetained</info>");
    }

    /**
     * Loop through the wikis and get the number of users retained.
     * @param string[] $dbNames Database names of the wikis to loop through.
     * @param DateTime $end Search from this day onward.
     * @param string[] $usernames Usernames to search for.
     * @return int
     */
    private function getNumUsersRetained(array $dbNames, DateTime $end, array $usernames): int
    {
        // Create and display progress bar for looping through wikis.
        if (null !== $this->output) {
            ProgressBar::setFormatDefinition(
                'custom',
                " <comment>%message%</comment>\n".
                " %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %memory:6s%\n"
            );
            $progress = new ProgressBar($this->output, count($dbNames));
            $progress->setFormat('custom');
            $progress->start();
        }

        $usersRetained = $this->getNumUsersRetainedInner($dbNames, $usernames, $end, $progress);

        // Clear out progress bar if we're running this from a Command.
        if (null !== $this->output) {
            $progress->setMessage('');
            $progress->finish();
        }

        if (true === $usersRetained) {
            $this->log(
                " <comment>Short-circuiting, all users have met retention threshold.</comment>\n"
            );
            return count($usernames);
        }

        return $usersRetained;
    }

    /**
     * Loop through the given databases and determine how many of the given users have made at least one edit after
     * the given date. If all users have met the retention threshold, the looping short-circuits and `true` is returned.
     * @param string[] $dbNames
     * @param string[] $usernames
     * @param DateTime $end
     * @param ProgressBar &$progress The progress bar, used when running from a Command.
     * @return int|bool Number of users retained, or true if all users have met the retention threshold.
     */
    private function getNumUsersRetainedInner(array $dbNames, array $usernames, DateTime $end, ?ProgressBar &$progress)
    {
        $usersRetained = [];

        foreach ($dbNames as $dbName) {
            if (null !== $this->output && null !== $progress) {
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
     * Persist an EventStat with given metric and value, or update the existing one, if present.
     * @param string $metric
     * @param mixed $value
     * @param int $offset Offset value associated with the metric, such as the number of days in evaluating retention.
     * @return EventStat
     */
    private function createOrUpdateEventStat(string $metric, $value, ?int $offset = null): EventStat
    {
        // Update class property.
        $this->stats[$metric] = [
            'value' => $value,
            'offset' => $offset,
        ];

        // Create or update an EventStat.
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => $metric,
            ]);

        if (null === $eventStat) {
            $eventStat = new EventStat($this->event, $metric, $value, $offset);
        } else {
            $eventStat->setValue($value);
        }

        $this->entityManager->persist($eventStat);

        return $eventStat;
    }

    /**
     * For the given EventWiki, create and persist an EventWikiStat with given metric and value,
     * or update the existing one, if present.
     * @param EventWiki $wiki
     * @param string $metric
     * @param mixed $value
     * @param int $offset Offset value associated with the metric, such as the number of days in evaluating retention.
     * @return EventWikiStat
     */
    private function createOrUpdateEventWikiStat(
        EventWiki $wiki,
        string $metric,
        $value,
        ?int $offset = null
    ): EventWikiStat {
        $domain = $wiki->getDomain();

        // Update class property.
        if (!isset($this->stats['wikis'][$domain])) {
            $this->stats['wikis'][$domain] = [];
        }
        $this->stats['wikis'][$domain][$metric] = [
            'value' => $value,
            'offset' => $offset,
        ];

        // Create or update an EventStat.
        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $wiki,
                'metric' => $metric,
            ]);

        if (null === $eventWikiStat) {
            $eventWikiStat = new EventWikiStat($wiki, $metric, $value, $offset);
        } else {
            $eventWikiStat->setValue($value);
        }

        $this->entityManager->persist($eventWikiStat);

        return $eventWikiStat;
    }

    /**
     * First grab the list of common wikis edited amongst all users,
     * so we don't unnecessarily query all wikis.
     * @param string[] $usernames
     * @return string[] Database names, ready to be used in a query.
     */
    private function getCommonWikis(array $usernames): array
    {
        if (isset($this->commonWikis)) {
            return $this->commonWikis;
        }

        $dbNames = $this->eventRepo->getCommonWikis($usernames);
        sort($dbNames);
        $this->commonWikis = $dbNames;
        return $this->commonWikis;
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
