<?php

namespace Tests\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Command\SpawnJobsCommand;
use AppBundle\Model\Event;
use AppBundle\Model\Job;
use DateTime;

/**
 * Tests for the SpawnJobsCommand.
 */
class SpawnJobsCommandTest extends KernelTestCase
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

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $this->entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();

        $this->fixtureExecutor = new ORMExecutor(
            $this->entityManager,
            new ORMPurger($this->entityManager)
        );

        $this->getFixtureLoader()->addFixture(new LoadFixtures('extended'));
        $this->fixtureExecutor->execute($this->getFixtureLoader()->getFixtures());

        // We need the event created in the fixtures.
        $this->event = $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        $application = new Application(self::$kernel);
        $application->add(new SpawnJobsCommand(self::$kernel->getContainer()));
        $command = $application->find('app:spawn-jobs');
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

        // We'll run some assertions on the Job class.
        $this->jobSpec($job);

        $this->spawnSpec($job);
    }

    /**
     * When there are no queued jobs.
     */
    private function nonexistentSpec()
    {
        $this->commandTester->execute([]);
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertContains('No jobs found in the queue', $output);
    }

    /**
     * Some post-persist assertions on the Job class, since
     * the dedicated JobTest class does not persist to the database.
     * @param Job $job
     */
    private function jobSpec(Job $job)
    {
        $this->assertTrue($job->getId() > 0);
        $this->assertEquals(
            (new DateTime())->format('Ymd'),
            $job->getSubmitted()->format('Ymd')
        );
        $this->assertFalse($job->getStarted());
    }

    /**
     * Spawning a new job. This is ran in dry mode so that calculations aren't
     * actually ran, since those assertions are made in ProcessEventCommandTest.
     * @param Job $job
     */
    private function spawnSpec(Job $job)
    {
        $this->commandTester->execute(['--dry' => true]);
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertContains('1 job(s) started successfully', $output);

        $this->assertTrue($job->getStarted());
    }
}
