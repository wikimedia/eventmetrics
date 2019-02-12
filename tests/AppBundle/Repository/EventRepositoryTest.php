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
    public function testGetPagesUsingFile():void
    {
        $kernel = static::bootKernel();
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $repo = new EventRepository($entityManager);
        $repo->setContainer($kernel->getContainer());

        static::assertGreaterThan(0, $repo->getPagesUsingFile('commonswiki_p', 'Ultrasonic_humidifier.jpg'));
        static::assertGreaterThan(0, $repo->getPagesUsingFile('enwiki_p', '2-cube.png'));
    }
}
