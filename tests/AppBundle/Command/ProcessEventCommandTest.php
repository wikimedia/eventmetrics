<?php
/**
 * This file contains only the ProcessEventCommandTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Command;

use AppBundle\Command\ProcessEventCommand;
use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\Job;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * Tests for the ProcessEventCommand.
 */
class ProcessEventCommandTest extends EventMetricsTestCase
{
    /**
     * @var ORMExecutor
     */
    private $fixtureExecutor;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var ContainerAwareLoader
     */
    private $fixtureLoader;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * Event created in the fixtures.
     * @var Event
     */
    private $event;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $container = self::$kernel->getContainer();

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $this->entityManager = $container->get('doctrine')->getManager();

        $this->fixtureExecutor = new ORMExecutor(
            $this->entityManager,
            new ORMPurger($this->entityManager)
        );

        $this->getFixtureLoader()->addFixture(new LoadFixtures('extended'));
        $this->fixtureExecutor->execute($this->getFixtureLoader()->getFixtures());

        $application = new Application(self::$kernel);
        $application->add(new ProcessEventCommand(
            $container,
            $container->get('AppBundle\Service\EventProcessor')
        ));
        $command = $application->find('app:process-event');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @return ContainerAwareLoader
     */
    private function getFixtureLoader(): ContainerAwareLoader
    {
        if (!$this->fixtureLoader) {
            $this->fixtureLoader = new ContainerAwareLoader(self::$kernel->getContainer());
        }
        return $this->fixtureLoader;
    }

    /**
     * @param string[] $conds
     */
    private function prepareEvent(array $conds = ['title' => 'Oliver_and_Company']): void
    {
        // We need the event created in the fixtures.
        $this->event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy($conds);
    }

    private function persistJob(): void
    {
        // Create a Job for the Event and flush it to the database.
        $job = new Job($this->event);
        $this->entityManager->persist($job);
        $this->entityManager->flush();
    }

    /**
     * Start of test suite, run the command and make the assertions.
     */
    public function testProcess(): void
    {
        $this->nonexistentSpec();

        $this->prepareEvent();
        $this->persistJob();
        $this->commandTester->execute(['eventId' => $this->event->getId()]);
        static::assertEquals(0, $this->commandTester->getStatusCode());

        $this->numEventStatsSpec();

        // Test each individual EventStat.
        $this->newEditorsSpec();
        $this->editCountSpec();
        $this->byteDifferenceSpec();
        $this->pagesCreatedSpec();
        $this->pagesImprovedSpec();
        $this->filesUploadedSpec();
        $this->fileUsageSpec();
        $this->itemsCreatedAndImprovedSpec();
        $this->retentionSpec();
        $this->pageviewsSpec();
        $this->jobFinishedSpec();
    }

    /**
     * Event that doesn't exist.
     */
    private function nonexistentSpec(): void
    {
        $this->commandTester->execute(['eventId' => 12345]);
        static::assertEquals(1, $this->commandTester->getStatusCode());
    }

    /**
     * Number of EventStat's created.
     */
    private function numEventStatsSpec(): void
    {
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findAll(['event' => $this->event]);
        static::assertEquals(15, count($eventStats));
    }

    /**
     * Number of new editors.
     */
    private function newEditorsSpec(): void
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'new-editors',
            ]);
        static::assertEquals(1, $eventStat->getValue());
    }

    /**
     * Number of edits.
     */
    private function editCountSpec(): void
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'edits',
            ]);
        static::assertEquals(20, $eventStat->getValue());
    }

    /**
     * Bytes difference.
     */
    private function byteDifferenceSpec(): void
    {
        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'byte-difference',
            ]);
        static::assertEquals(12806, $eventWikiStat->getValue());

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'byte-difference',
            ]);
        static::assertEquals(12806, $eventStat->getValue());
    }

    /**
     * Number of pages created.
     */
    private function pagesCreatedSpec(): void
    {
        // As an EventStat...
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-created',
            ]);
        static::assertEquals(3, $eventStat->getValue());

        // As an EventWikiStat...
        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'pages-created',
            ]);
        static::assertEquals(3, $eventWikiStat->getValue());
    }

    /**
     * Number of pages improved.
     */
    private function pagesImprovedSpec(): void
    {
        // As an EventStat...
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-improved',
            ]);
        static::assertEquals(6, $eventStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'pages-improved',
            ]);
        static::assertEquals(6, $eventWikiStat->getValue());
    }

    /**
     * Files uploaded.
     */
    private function filesUploadedSpec(): void
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'files-uploaded',
            ]);
        static::assertEquals(3, $eventStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('commons.wikimedia'),
                'metric' => 'files-uploaded',
            ]);
        static::assertEquals(1, $eventWikiStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'files-uploaded',
            ]);
        static::assertEquals(2, $eventWikiStat->getValue());
    }

    /**
     * File usage.
     */
    private function fileUsageSpec(): void
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'file-usage',
            ]);

        // Used on [[Domino Park]], [[As Long as It Matters]], and [[Need to Be Next to You]],
        // but there could eventually be others.
        static::assertGreaterThan(2, $eventStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('commons.wikimedia'),
                'metric' => 'file-usage',
            ]);
        static::assertGreaterThan(0, $eventWikiStat->getValue());

        // Used on [[As Long as It Matters]] and [[Need to Be Next to You]], eventually there may be more.
        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'file-usage',
            ]);
        static::assertGreaterThan(1, $eventWikiStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'pages-using-files',
            ]);
        static::assertGreaterThan(0, $eventWikiStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('commons.wikimedia'),
                'metric' => 'pages-using-files',
            ]);
        static::assertGreaterThan(0, $eventWikiStat->getValue());
    }

    /**
     * Items created and improved (should be the same for event and event-wiki).
     * @covers \AppBundle\Service\EventProcessor::setItemsCreatedOrImprovedOnWikidata()
     */
    protected function itemsCreatedAndImprovedSpec(): void
    {
        $eventStat = $this->entityManager->getRepository('Model:EventStat');
        $eventWikiStat = $this->entityManager->getRepository('Model:EventWikiStat');

        // Event-wiki stats.
        $wikidata = $this->event->getWikiByDomain('www.wikidata');
        $ewItemsCreatedStat = $eventWikiStat->findOneBy(['wiki' => $wikidata, 'metric' => 'items-created']);
        static::assertEquals(2, $ewItemsCreatedStat->getValue());
        $ewItemsImprovedStat = $eventWikiStat->findOneBy(['wiki' => $wikidata, 'metric' => 'items-improved']);
        static::assertEquals(2, $ewItemsImprovedStat->getValue());

        // Event stats.
        $eItemsCreatedStat = $eventStat->findOneBy(['event' => $this->event, 'metric' => 'items-created']);
        static::assertEquals(2, $eItemsCreatedStat->getValue());
        $eItemsImprovedStat = $eventStat->findOneBy(['event' => $this->event, 'metric' => 'items-improved']);
        static::assertEquals(2, $eItemsImprovedStat->getValue());
    }

    /**
     * Retention.
     */
    private function retentionSpec(): void
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'retention',
            ]);
        static::assertEquals(1, $eventStat->getValue());
    }

    /**
     * Pageviews.
     */
    protected function pageviewsSpec(): void
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-created-pageviews',
            ]);
        static::assertGreaterThan(18932, $eventStat->getValue());

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-using-files-pageviews-avg',
            ]);
        static::assertGreaterThan(0, $eventStat->getValue());
    }

    /**
     * There should be no pending jobs.
     */
    private function jobFinishedSpec(): void
    {
        $jobs = $this->entityManager
            ->getRepository('Model:Job')
            ->findOneBy([
                'event' => $this->event,
            ]);
        static::assertNull($jobs);
    }

    /**
     * Creates a new job, this time with EventCategorys on the Event.
     */
    public function testCategories(): void
    {
        $this->prepareEvent();
        // Add https://en.wikipedia.org/wiki/Category:Parks_in_Brooklyn.
        // This will include [[Domino Park]] created and edited by MusikAnimal.
        new EventCategory($this->event, 'Parks in Brooklyn', 'en.wikipedia');

        $this->persistJob();
        $this->commandTester->execute(['eventId' => $this->event->getId()]);

        // Should be only 1 page created, ([[Domino Park]]).
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-created',
            ]);
        static::assertEquals(1, $eventStat->getValue());

        // We don't count edits to created pages as 'improved'.
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-improved',
            ]);
        static::assertEquals(0, $eventStat->getValue());
    }

    /**
     * Event with a category and no participants.
     */
    public function testEventsWithoutParticipants(): void
    {
        $this->prepareEvent(['title' => 'Event_without_participants']);
        $this->persistJob();
        $this->commandTester->execute(['eventId' => $this->event->getId()]);
        static::assertEquals(0, $this->commandTester->getStatusCode());

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'participants',
            ]);
        static::assertEquals(6, $eventStat->getValue());

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'new-editors',
            ]);
        static::assertEquals(1, $eventStat->getValue());

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-improved',
            ]);
        static::assertEquals(1, $eventStat->getValue());

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'files-uploaded',
            ]);
        static::assertEquals(3, $eventStat->getValue());
    }
}
