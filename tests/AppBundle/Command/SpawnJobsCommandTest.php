<?php
/**
 * This file contains only the SpawnJobsCommandTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Command;

use AppBundle\Command\SpawnJobsCommand;
use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\Job;
use DateTime;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the SpawnJobsCommand.
 */
class SpawnJobsCommandTest extends GrantMetricsTestCase
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
        $application->add(new SpawnJobsCommand(
            self::$kernel->getContainer(),
            self::$kernel->getContainer()->get('AppBundle\Service\JobHandler')
        ));
        $command = $application->find('app:spawn-jobs');
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

        // We'll run some assertions on the Job class.
        $this->jobSpec($job);

        $this->spawnSpec($job);

        // Revive the job and run once more.
        $job->setStarted(false);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->spawnOneSpec($job);
    }

    /**
     * When there are no queued jobs.
     */
    private function nonexistentSpec(): void
    {
        $this->commandTester->execute([]);
        static::assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        static::assertContains('No jobs found in the queue', $output);
    }

    /**
     * Some post-persist assertions on the Job class, since
     * the dedicated JobTest class does not persist to the database.
     * @param Job $job
     */
    private function jobSpec(Job $job): void
    {
        static::assertTrue($job->getId() > 0);
        static::assertEquals(
            (new DateTime())->format('Ymd'),
            $job->getSubmitted()->format('Ymd')
        );
        static::assertFalse($job->getStarted());
    }

    /**
     * Spawning all jobs.
     * @param Job $job
     */
    private function spawnSpec(Job $job): void
    {
        $this->commandTester->execute([]);
        static::assertTrue($job->getStarted());
        $output = $this->commandTester->getDisplay();
        static::assertContains('Event statistics successfully saved', $output);
        static::assertEquals(0, $this->commandTester->getStatusCode());
    }

    /**
     * Spawning a single job.
     * @param Job $job
     */
    private function spawnOneSpec(Job $job): void
    {
        // First try bogus job ID.
        $this->commandTester->execute(['--id' => 12345]);
        $output = $this->commandTester->getDisplay();
        static::assertContains('No job found', $output);
        static::assertEquals(1, $this->commandTester->getStatusCode());

        $this->commandTester->execute(['--id' => $job->getId()]);
        static::assertTrue($job->getStarted());
        $output = $this->commandTester->getDisplay();
        static::assertContains('Event statistics successfully saved', $output);
        static::assertEquals(0, $this->commandTester->getStatusCode());
    }
}
