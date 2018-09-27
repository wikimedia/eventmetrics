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
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the ProcessEventCommand.
 */
class ProcessEventCommandTest extends GrantMetricsTestCase
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

        // We need the event created in the fixtures.
        $this->event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

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
     * Start of test suite, run the command and make the assertions.
     */
    public function testProcess(): void
    {
        $this->nonexistentSpec();

        // Create a Job for the Event and flush it to the database.
        $job = new Job($this->event);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->commandTester->execute(['eventId' => $this->event->getId()]);
        static::assertEquals(0, $this->commandTester->getStatusCode());

        $this->numEventStatsSpec();

        // Test each individual EventStat.
        $this->newEditorsSpec();
        $this->pagesCreatedSpec();
        $this->pagesImprovedSpec();
        $this->filesUploadedSpec();
        $this->fileUsageSpec();
        $this->itemsCreatedAndImprovedSpec();
        $this->retentionSpec();
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
        static::assertEquals(8, count($eventStats));
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
        static::assertEquals(1, $eventStat->getValue());

        // As an EventWikiStat...
        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'pages-created',
            ]);
        static::assertEquals(1, $eventWikiStat->getValue());
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
        static::assertEquals(7, $eventStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('en.wikipedia'),
                'metric' => 'pages-improved',
            ]);
        static::assertEquals(7, $eventWikiStat->getValue());
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
        static::assertEquals(1, $eventStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('commons.wikimedia'),
                'metric' => 'files-uploaded',
            ]);
        static::assertEquals(1, $eventWikiStat->getValue());
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
        // Used at least on [[Domino Park]], but there could eventually be others.
        static::assertGreaterThan(0, $eventStat->getValue());

        $eventWikiStat = $this->entityManager
            ->getRepository('Model:EventWikiStat')
            ->findOneBy([
                'wiki' => $this->event->getWikiByDomain('commons.wikimedia'),
                'metric' => 'file-usage',
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
        static::assertEquals(3, $ewItemsCreatedStat->getValue());
        $ewItemsImprovedStat = $eventWikiStat->findOneBy(['wiki' => $wikidata, 'metric' => 'items-improved']);
        static::assertEquals(5, $ewItemsImprovedStat->getValue());

        // Event stats.
        $eItemsCreatedStat = $eventStat->findOneBy(['event' => $this->event, 'metric' => 'items-created']);
        static::assertEquals(3, $eItemsCreatedStat->getValue());
        $eItemsImprovedStat = $eventStat->findOneBy(['event' => $this->event, 'metric' => 'items-improved']);
        static::assertEquals(5, $eItemsImprovedStat->getValue());
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
        // Add https://en.wikipedia.org/wiki/Category:Parks_in_Brooklyn.
        // This will include [[Domino Park]] created and edited by MusikAnimal.
        new EventCategory($this->event, 'Parks in Brooklyn', 'en.wikipedia');

        // Create a Job for the Event and flush it to the database.
        $job = new Job($this->event);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->commandTester->execute(['eventId' => $this->event->getId()]);

        // Should be only 1 page created and improved, ([[Domino Park]]).
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-created',
            ]);
        static::assertEquals(1, $eventStat->getValue());
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-improved',
            ]);
        static::assertEquals(1, $eventStat->getValue());
    }
}
