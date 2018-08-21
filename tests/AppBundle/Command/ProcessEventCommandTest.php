<?php
/**
 * This file contains only the ProcessEventCommandTest class.
 */

namespace Tests\AppBundle\Command;

use AppBundle\Model\EventCategory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Command\ProcessEventCommand;
use AppBundle\Model\Job;
use AppBundle\Model\Event;
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

    public function setUp()
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
    private function getFixtureLoader()
    {
        if (!$this->fixtureLoader) {
            $this->fixtureLoader = new ContainerAwareLoader(self::$kernel->getContainer());
        }
        return $this->fixtureLoader;
    }

    /**
     * Start of test suite, run the command and make the assertions.
     */
    public function testProcess()
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
        $this->retentionSpec();
        $this->jobFinishedSpec();
    }

    /**
     * Event that doesn't exist.
     */
    private function nonexistentSpec()
    {
        $this->commandTester->execute(['eventId' => 12345]);
        static::assertEquals(1, $this->commandTester->getStatusCode());
    }

    /**
     * Number of EventStat's created.
     */
    private function numEventStatsSpec()
    {
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findAll(['event' => $this->event]);
        static::assertEquals(6, count($eventStats));
    }

    /**
     * Number of new editors.
     */
    private function newEditorsSpec()
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'new-editors'
            ]);
        static::assertEquals(1, $eventStat->getValue());
    }

    /**
     * Number of pages created.
     */
    private function pagesCreatedSpec()
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
    private function pagesImprovedSpec()
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
    private function filesUploadedSpec()
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
    private function fileUsageSpec()
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
     * Retention.
     */
    private function retentionSpec()
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'retention'
            ]);
        static::assertEquals(1, $eventStat->getValue());
    }

    /**
     * There should be no pending jobs.
     */
    private function jobFinishedSpec()
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
    public function testCategories()
    {
        $ew = $this->event->getWikiByDomain('en.wikipedia');

        // Add https://en.wikipedia.org/wiki/Category:Parks_in_Brooklyn.
        // This will include [[Domino Park]] created and edited by MusikAnimal.
        new EventCategory($ew, 234634);

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
