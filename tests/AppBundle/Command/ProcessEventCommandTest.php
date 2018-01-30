<?php

namespace Tests\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Command\ProcessEventCommand;
use AppBundle\Model\Job;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;

/**
 * Tests for the ProcessEventCommand.
 */
class ProcessEventCommandTest extends KernelTestCase
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

    /**
     * Whether or not we're testing against the Wikimedia replicas.
     * @var bool
     */
    private $isWikimedia;

    public function setUp()
    {
        self::bootKernel();

        $this->isWikimedia = (bool)self::$kernel
            ->getContainer()
            ->getParameter('database_replica_is_wikimedia');

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $this->entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();

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
        $application->add(new ProcessEventCommand(self::$kernel->getContainer()));
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
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $this->numEventStatsSpec();

        // Test each individual EventStat.
        $this->newEditorsSpec();
        $this->pagesCreatedSpec();
        $this->pagesImprovedSpec();
        $this->retentionSpec();

        $this->jobFinishedSpec();

        // For another Event with wider date range, in which all
        // participants are considered active.
        $event2 = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'The_Lion_King']);
        $this->commandTester->execute(['eventId' => $event2->getId()]);

        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $event2,
                'metric' => 'retention'
            ]);
        $this->assertEquals(3, $eventStat->getValue());
    }

    /**
     * Event that doesn't exist.
     */
    private function nonexistentSpec()
    {
        $this->commandTester->execute(['eventId' => 12345]);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    /**
     * Number of EventStat's created.
     */
    private function numEventStatsSpec()
    {
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findAll(['event' => $this->event]);
        $this->assertEquals(4, count($eventStats));
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
        $this->assertEquals(1, $eventStat->getValue());
    }

    /**
     * Number of pages created.
     */
    private function pagesCreatedSpec()
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-created'
            ]);
        $this->assertEquals($this->isWikimedia ? 7 : 2, $eventStat->getValue());
    }

    /**
     * Number of pages improved.
     */
    private function pagesImprovedSpec()
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'pages-improved'
            ]);
        $this->assertEquals($this->isWikimedia ? 1330 : 3, $eventStat->getValue());
    }

    /**
     * Number of pages improved.
     */
    private function retentionSpec()
    {
        $eventStat = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findOneBy([
                'event' => $this->event,
                'metric' => 'retention'
            ]);
        $this->assertEquals($this->isWikimedia ? 3 : 1, $eventStat->getValue());
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
        $this->assertEquals(0, count($jobs));
    }
}
