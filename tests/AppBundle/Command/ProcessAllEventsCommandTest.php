<?php

declare(strict_types=1);

namespace Tests\AppBundle\Command;

use AppBundle\Command\ProcessAllEventsCommand;
use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Participant;
use AppBundle\Model\Program;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\AppBundle\Controller\DatabaseAwareWebTestCase;

/**
 * Tests for ProcessAllEventsCommand.
 */
class ProcessAllEventsCommandTest extends DatabaseAwareWebTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var Program
     */
    private $program;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $container = self::$kernel->getContainer();

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $this->entityManager = $container->get('doctrine')->getManager();

        // Load basic fixtures containing the example program.
        $this->addFixture(new LoadFixtures());
        $this->executeFixtures();

        $this->program = $this->entityManager->getRepository('Model:Program')
            ->findOneBy(['title' => 'My_fun_program']);

        $application = new Application(self::$kernel);
        $application->add(new ProcessAllEventsCommand(
            $container,
            $container->get('AppBundle\Service\JobHandler')
        ));
        $command = $application->find('app:process-all-events');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * Create some sample Events and run the command, making sure the Jobs were created.
     */
    public function testProcessAllEvents(): void
    {
        // Create a valid Event with User:Example as a participant.
        $validEvent = new Event(
            $this->program,
            'Valid event',
            new \DateTime('2015-01-01'),
            new \DateTime('2015-01-02')
        );
        new Participant($validEvent, 27666025);
        new EventWiki($validEvent, 'en.wikipedia');
        $this->entityManager->persist($validEvent);

        // An invalid Event for which a Job should not be created.
        $invalidEvent = new Event(
            $this->program,
            'Invalid event',
            new \DateTime('2050-01-01'),
            new \DateTime('2050-01-02')
        );
        $this->entityManager->persist($invalidEvent);

        $this->entityManager->flush();

        // Execute the Command. We use the --no-spawn command so the Job records will remain for testing purposes.
        $this->commandTester->execute(['--no-spawn' => true]);

        // Jobs should be created for only one Event.
        $jobs = $this->entityManager->getRepository('Model:Job')->findAll();

        static::assertCount(1, $jobs);
    }
}
