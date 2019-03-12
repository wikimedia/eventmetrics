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
use Symfony\Component\Stopwatch\Stopwatch;

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

    /** @var string[] Array with usernames of editors from page IDs as keys */
    private $implicitEditors = [];

    /** @var string[] Unique wikis where participants have made edits. */
    protected $commonWikis;

    /** @var int Number of edits made during the event. */
    private $edits = 0;

    /** @var int Number of pages edited during the event. */
    private $pagesImproved = 0;

    /** @var int Number of pages created. */
    private $pagesCreated = 0;

    /** @var int Bytes changed. */
    private $byteDifference = 0;

    /** @var int Files uploaded. */
    private $filesUploaded = 0;

    /** @var int Number of pages that use the uploaded files. */
    private $fileUsage = 0;

    /** @var int Number of pages using uploaded files. */
    private $pagesUsingFiles = 0;

    /** @var mixed[][] Array containing arrays with keys 'dbName' and 'pageId']. */
    private $pageTitlesUsingFiles = [];

    /** @var Stopwatch used to profile performance. */
    private $stopwatch;

    /**
     * Constructor for the EventProcessor.
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     * @param Stopwatch $stopwatch
     */
    public function __construct(LoggerInterface $logger, ContainerInterface $container, Stopwatch $stopwatch)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->stopwatch = $stopwatch;
    }

    /**
     * Generate and store statistics for the given event.
     * @param Event $event
     * @param OutputInterface|null &$output Used by Commands so that the output can be controlled by the parent process.
     *   If this is null, a local LoggerInterface is used instead.
     */
    public function process(Event $event, ?OutputInterface &$output = null): void
    {
        $this->loadEvent($event);

        $this->output = &$output;

        $this->log('Processing event '.$event->getId());

        // Generate and persist each type of EventStat/EventWikiStat.

        // If there is an associated EventWiki that represents a family, we need to first find
        // the common wikis where participants have edited. From there we will create new EventWikis
        // if needed, and later remove those that have no statistics so that the UI is clean.
        $this->createEventWikisFromLang();

        $this->setContributionStats();

        // This must be called after setContributionStats, because that method sets the page IDs.
        $this->setPageviewsStats();

        // Remove EventWikis that are part of a family where there are no statistics.
        $this->removeEventWikisWithNoStats();

        $this->setParticipants();

        $this->setNewEditors();

        $this->setRetention();

        // Clear out any existing job records from the queue.
        $event->clearJobs();

        // Update the 'updated' attribute.
        $event->setUpdated(new DateTime());
        $this->entityManager->persist($event);

        // Flush to the database.
        $this->entityManager->flush();

        $this->log("\n<info>Event statistics successfully saved.</info>\n");
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
                    // Doctrine wants you to both remove from the Event and from the entity manager.
                    $this->event->removeWiki($wiki);
                    $this->entityManager->remove($wiki);
                }
            }
        }
    }

    /**
     * Compute and persist the number of participants.
     */
    private function setParticipants(): void
    {
        $logKey = 'participants';
        $this->logStart("\nFetching number of participants...", $logKey);
        $usernames = $this->getParticipantNames() ?: $this->implicitEditors;
        $count = count($usernames);
        $this->createOrUpdateEventStat('participants', $count);
        $this->logEnd($logKey);
        $this->log(">> <info>Participants: $count</info>");
    }

    /**
     * Compute and persist a new EventStat for the number of new editors.
     */
    private function setNewEditors(): void
    {
        $logKey = 'new_editors';
        $this->logStart("\nFetching number of new editors...", $logKey);
        $numNewEditors = count($this->getNewEditors());
        $newEditorOffset = Event::getAllAvailableMetrics()['new-editors'];
        $this->createOrUpdateEventStat('new-editors', $numNewEditors, $newEditorOffset);
        $this->logEnd($logKey);
        $this->log(">> <info>New editors: $numNewEditors</info>");
    }

    /**
     * Get the usernames of the new editors.
     * @return string[]
     */
    private function getNewEditors(): array
    {
        if (!is_array($this->newEditors)) {
            $usernames = $this->getParticipantNames() ?: $this->implicitEditors;
            $this->newEditors = $this->eventRepo->getNewEditors($this->event, $usernames);
        }
        return $this->newEditors;
    }

    /**
     * Set pageviews stats of pages created and improved (the latter as an average over up to the last 30 days).
     */
    protected function setPageviewsStats(): void
    {
        $this->log("\nFetching pageviews...");

        /** @var EventWikiRepository $ewRepo */
        $ewRepo = $this->entityManager->getRepository('Model:EventWiki');
        $ewRepo->setContainer($this->container);

        $start = $this->event->getStartUTC();
        $pageviewsCreatedTotal = 0;
        $avgPageviewsImprovedTotal = 0;

        foreach ($this->event->getWikis() as $wiki) {
            // No pageviews for anything other than Wikipedias.
            if ($wiki->isFamilyWiki() || 'wikipedia' !== $wiki->getFamilyName()) {
                continue;
            }

            $logKey = 'pageviews_'.$wiki->getDomain();
            $this->logStart("> Fetching pageviews for {$wiki->getDomain()}...", $logKey);

            $dbName = $ewRepo->getDbNameFromDomain($wiki->getDomain());
            $pageviewsCreated = $ewRepo->getPageviews($dbName, $wiki->getDomain(), $start, $wiki->getPagesCreated());
            $avgPageviewsImproved = $ewRepo->getPageviews(
                $dbName,
                $wiki->getDomain(),
                $start,
                $wiki->getPagesEdited(),
                true
            );

            $pageviewsCreatedTotal += $pageviewsCreated;
            $avgPageviewsImprovedTotal += $avgPageviewsImproved;

            $this->logEnd($logKey);

            $this->createOrUpdateEventWikiStat($wiki, 'pages-created-pageviews', $pageviewsCreated);
            $this->createOrUpdateEventWikiStat($wiki, 'pages-improved-pageviews-avg', $avgPageviewsImproved);
        }

        $avgPageviewsPagesUsingFiles = $this->getFilePageviews($ewRepo, $start);

        $this->createOrUpdateEventStat('pages-created-pageviews', $pageviewsCreatedTotal);
        $this->createOrUpdateEventStat('pages-improved-pageviews-avg', $avgPageviewsImprovedTotal);
        $this->createOrUpdateEventStat('pages-using-files-pageviews-avg', $avgPageviewsPagesUsingFiles);
        $this->log(">> <info>Pageviews of pages created: $pageviewsCreatedTotal</info>");
        $this->log(">> <info>Average daily pageviews of pages edited: $avgPageviewsImprovedTotal</info>");
        $this->log(">> <info>Average daily pageviews to pages using files: $avgPageviewsPagesUsingFiles</info>");
    }

    /**
     * Set the pageviews of files uploaded, across all wikis.
     * @param EventWikiRepository $ewRepo
     * @param DateTime $start
     * @return int
     */
    private function getFilePageviews(EventWikiRepository $ewRepo, DateTime $start): int
    {
        $logKey = 'pageviews_files_uploaded';
        $this->logStart("> Fetching pageviews of pages containing files uploaded...", $logKey);

        /** @var array $pageIdsByDbName Keys are dbNames, values is an array of page IDs. */
        $pageIdsByDbName = [];
        $avgPageviewsPagesUsingFiles = 0;

        // First group by database.
        foreach ($this->pageTitlesUsingFiles as $row) {
            if (!isset($pageIdsByDbName[$row['dbName']])) {
                $pageIdsByDbName[$row['dbName']] = [$row['pageId']];
            } else {
                $pageIdsByDbName[$row['dbName']][] = $row['pageId'];
            }
        }

        foreach ($pageIdsByDbName as $dbName => $pageIds) {
            $domain = $ewRepo->getDomainFromEventWikiInput($dbName);
            $avgPageviewsPagesUsingFiles += $ewRepo->getPageviews($dbName, $domain, $start, $pageIds, true);
        }

        $this->logEnd($logKey);

        return $avgPageviewsPagesUsingFiles;
    }

    /**
     * Compute and persist a new EventStat and EventWikiStats
     * for the number of pages created/improved.
     */
    private function setContributionStats(): void
    {
        $this->log("\nFetching number of pages created or improved...");

        /** @var EventWikiRepository $ewRepo */
        $ewRepo = $this->entityManager->getRepository('Model:EventWiki');
        $ewRepo->setContainer($this->container);

        /** @var bool $saveEventStats Whether or not EventWikiStats are being saved. */
        $saveEventStats = false;

        foreach ($this->event->getWikis() as $wiki) {
            // No stats for EventWikis that represent a family.
            if ($wiki->isFamilyWiki()) {
                continue;
            }

            // Different stats based on wiki family.
            switch ($wiki->getFamilyName()) {
                case 'wikipedia':
                    $this->setContributionsWikipedias($wiki, $ewRepo);
                    $this->setFilesUploaded($wiki, $ewRepo);
                    $saveEventStats = true;
                    break;
                case 'commons':
                    $this->setFilesUploaded($wiki, $ewRepo);
                    $saveEventStats = true;
                    break;
                case 'wikidata':
                    $this->setItemsCreatedOrImprovedOnWikidata($wiki, $ewRepo);
                    break;
            }

            $this->setUserCounts($wiki, $ewRepo);
        }

        // Only save some metrics as EventStats if they were also saved as EventWikiStats.
        if ($saveEventStats) {
            $this->createOrUpdateEventStat('edits', $this->edits);
            $this->createOrUpdateEventStat('pages-created', $this->pagesCreated);
            $this->createOrUpdateEventStat('pages-improved', $this->pagesImproved);
            $this->createOrUpdateEventStat('byte-difference', $this->byteDifference);
            $this->createOrUpdateEventStat('files-uploaded', $this->filesUploaded);
            $this->createOrUpdateEventStat('file-usage', $this->fileUsage);
            $this->createOrUpdateEventStat('pages-using-files', $this->pagesUsingFiles);
        }

        $this->log(">> <info>Edits: {$this->edits}</info>");
        $this->log(">> <info>Pages created: {$this->pagesCreated}</info>");
        $this->log(">> <info>Pages improved: {$this->pagesImproved}</info>");
        $this->log(">> <info>Bytes added: {$this->byteDifference}</info>");
        $this->log(">> <info>Files uploaded: {$this->filesUploaded}</info>");
        $this->log(">> <info>Files used: {$this->fileUsage}</info>");
        $this->log(">> <info>Pages using uploaded files: {$this->pagesUsingFiles}</info>");
    }

    /**
     * Set pages created/improved for the given Wikipedia.
     * @param EventWiki $wiki
     * @param EventWikiRepository $ewRepo
     */
    private function setContributionsWikipedias(EventWiki $wiki, EventWikiRepository $ewRepo): void
    {
        $this->log("> Fetching pages created or improved on {$wiki->getDomain()}...");
        $dbName = $ewRepo->getDbNameFromDomain($wiki->getDomain());
        $start = $this->event->getStartUTC();
        $end = $this->event->getEndUTC();
        $usernames = $this->getParticipantNames();
        $categoryTitles = $this->event->getCategoryTitlesForWiki($wiki);

        $logKey = 'page_ids'.$wiki->getDomain();
        $this->logStart(">> Fetching page IDs...", $logKey);
        $pageIdsCreated = $ewRepo->getPageIds($dbName, $start, $end, $usernames, $categoryTitles, 'created');
        $pageIdsEdited = $ewRepo->getPageIds($dbName, $start, $end, $usernames, $categoryTitles, 'edited');

        // Set on the EventWiki, so this will get persisted to the database.
        $wiki->setPagesCreated($pageIdsCreated);
        $wiki->setPagesEdited($pageIdsEdited);

        $pageIds = array_merge($pageIdsCreated, $pageIdsEdited);
        $totalEditCount = $this->eventRepo->getTotalEditCount($dbName, $pageIds, $start, $end, $usernames);

        $this->logEnd($logKey);

        $logKey = 'bytes_changed';
        $this->logStart(">> Fetching bytes changed...", $logKey);
        $diff = $ewRepo->getBytesChanged($this->event, $dbName, $pageIds, $usernames);

        $totalCreated = count($pageIdsCreated);
        $totalEdited = count($pageIdsEdited);
        $this->pagesCreated += $totalCreated;
        $this->pagesImproved += $totalEdited;
        $this->edits += $totalEditCount;
        $this->byteDifference += $diff;

        $this->logEnd($logKey);

        $this->createOrUpdateEventWikiStat($wiki, 'edits', $totalEditCount);
        $this->createOrUpdateEventWikiStat($wiki, 'pages-created', $totalCreated);
        $this->createOrUpdateEventWikiStat($wiki, 'pages-improved', $totalEdited);
        $this->createOrUpdateEventWikiStat($wiki, 'byte-difference', $diff);
    }

    /**
     * Set the number of files uploaded on the given wiki.
     * @param EventWiki $wiki
     * @param EventWikiRepository $ewRepo
     */
    private function setFilesUploaded(EventWiki $wiki, EventWikiRepository $ewRepo): void
    {
        $logKey = 'files_uploaded_'.$wiki->getDomain();
        $this->logStart("> Fetching files uploaded on {$wiki->getDomain()} and global file usage...", $logKey);

        $dbName = $ewRepo->getDbNameFromDomain($wiki->getDomain());
        $start = $this->event->getStartUTC();
        $end = $this->event->getEndUTC();

        $ret = $this->eventRepo->getFilesUploaded($dbName, $start, $end, $this->getParticipantNames());
        $this->createOrUpdateEventWikiStat($wiki, 'files-uploaded', $ret);
        $this->filesUploaded += $ret;

        $ret = $this->eventRepo->getUsedFiles($dbName, $start, $end, $this->getParticipantNames());
        $this->createOrUpdateEventWikiStat($wiki, 'file-usage', $ret);
        $this->fileUsage += $ret;

        $ret = $this->eventRepo->getPagesUsingFiles($dbName, $start, $end, $this->getParticipantNames());
        $this->createOrUpdateEventWikiStat($wiki, 'pages-using-files', count($ret));
        $this->pagesUsingFiles += count($ret);
        $this->pageTitlesUsingFiles = array_merge($this->pageTitlesUsingFiles, $ret);

        $this->logEnd($logKey);
    }

    /**
     * Retrieve counts of Wikidata Items created or improved and save them as both event and event-wiki stats.
     * @param EventWiki $wiki The wiki (must always be Wikidata).
     * @param EventWikiRepository $ewRepo
     */
    private function setItemsCreatedOrImprovedOnWikidata(EventWiki $wiki, EventWikiRepository $ewRepo): void
    {
        $logKey = 'wikidata_items';
        $this->logStart("> Fetching items created or improved on Wikidata...", $logKey);

        $dbName = 'wikidatawiki_p';
        $start = $this->event->getStartUTC();
        $end = $this->event->getEndUTC();
        $usernames = $this->getParticipantNames();
        $categoryTitles = $this->event->getCategoryTitlesForWiki($wiki);
        $pageIdsCreated = $ewRepo->getPageIds($dbName, $start, $end, $usernames, $categoryTitles, 'created');
        $pageIdsEdited = $ewRepo->getPageIds($dbName, $start, $end, $usernames, $categoryTitles, 'edited');

        // Set on the EventWiki, so this will get persisted to the database.
        $wiki->setPagesCreated($pageIdsCreated);
        $wiki->setPagesEdited($pageIdsEdited);

        $this->logEnd($logKey);

        // Report the counts, and record them both for this wiki and the event (there's only ever one Wikidata wiki).
        $totalCreated = count($pageIdsCreated);
        $totalEdited = count($pageIdsEdited);
        $this->log(">> <info>Items created: $totalCreated</info>");
        $this->log(">> <info>Items improved: $totalEdited</info>");
        $this->createOrUpdateEventWikiStat($wiki, 'items-created', $totalCreated);
        $this->createOrUpdateEventWikiStat($wiki, 'items-improved', $totalEdited);
        $this->createOrUpdateEventStat('items-created', $totalCreated);
        $this->createOrUpdateEventStat('items-improved', $totalEdited);
    }

    /**
     * If current event is category based, find out participants and add them to
     * $this->implicitEditors.
     * @param EventWiki $wiki
     * @param EventWikiRepository $ewRepo
     */
    private function setUserCounts(EventWiki $wiki, EventWikiRepository $ewRepo): void
    {
        $logKey = 'user_counts';
        $this->logStart("> Fetching user counts for {$wiki->getDomain()}...", $logKey);

        $dbName = $ewRepo->getDbNameFromDomain($wiki->getDomain());

        $pageIds = $wiki->getPages();
        $usernames = $this->getParticipantNames();
        if ($pageIds && !$usernames && $this->event->getNumCategories() > 0) {
            $usernames = $ewRepo->getUsersFromPageIDs($dbName, $pageIds, $this->event);
            $this->implicitEditors += array_flip($usernames);
        }

        $this->logEnd($logKey);
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
        $logKey = 'retention';
        $this->logStart("\nFetching retention...\n", $logKey);

        $retentionOffset = Event::getAllAvailableMetrics()['retention'];
        $end = $this->event->getEndUTC()->modify("+$retentionOffset days");

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

        $this->logEnd($logKey);
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
     * @param bool $inline If set, a new line will not be appended to $message.
     *
     * This is simple logging. The LoggerInterface portion cannot easily
     * be tested, but the output via $this->output does have test coverage.
     * @codeCoverageIgnore
     */
    private function log(string $message, bool $inline = false): void
    {
        if (null === $this->output) {
            $this->logger->info($message);
        } elseif ($inline) {
            $this->output->write($message);
        } else {
            $this->output->writeln($message);
        }
    }

    /**
     * Log a message and start the stopwatch.
     * @param string $message
     * @param string $key Unique key for this profile.
     */
    private function logStart(string $message, string $key): void
    {
        // Show message inline, later $this->logDone() will be called that adds a new line.
        $this->log($message, true);

        // Start profiling runtime.
        $this->stopwatch->start($key);
    }

    /**
     * Declare the profile done, stopping the stop watch and logging the runtime.
     * @param string $key Unique key for this profile.
     * @param string|null $message
     */
    private function logEnd(string $key, ?string $message = null): void
    {
        $this->stopwatch->stop($key);
        $message = $message ?? 'Done';
        $duration = round($this->stopwatch->getEvent($key)->getDuration(), 2);
        $this->log(" <comment>$message ($duration ms)</comment>");
    }
}
