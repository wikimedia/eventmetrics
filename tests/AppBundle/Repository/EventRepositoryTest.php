<?php
declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Repository\EventRepository;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * @covers \AppBundle\Repository\EventRepository
 */
class EventRepositoryTest extends EventMetricsTestCase
{
    /** @var EventRepository */
    private $repo;

    public function setUp(): void
    {
        $kernel = static::bootKernel();
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repo = new EventRepository($entityManager);
        $this->repo->setContainer($kernel->getContainer());

        parent::setUp();
    }

    public function testGetPagesUsingFile():void
    {
        static::assertGreaterThan(0, $this->repo->getPagesUsingFile('commonswiki_p', 'Ultrasonic_humidifier.jpg'));
        static::assertGreaterThan(0, $this->repo->getPagesUsingFile('enwiki_p', '2-cube.png'));
    }
}
