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

    /**
     * @covers \AppBundle\Repository\EventRepository::getPagesUsingFile()
     */
    public function testGetPagesUsingFile(): void
    {
        static::assertGreaterThan(0, $this->repo->getPagesUsingFile('commonswiki_p', 'Ultrasonic_humidifier.jpg'));
        static::assertGreaterThan(0, $this->repo->getPagesUsingFile('enwiki_p', '2-cube.png'));
    }

    /**
     * @covers \AppBundle\Repository\EventRepository::getUsedFiles()
     */
    public function testGetUsedFiles(): void
    {
        // 74025845 = [[File:XTools service overloaded error page.png]] (should never be in mainspace).
        static::assertEquals(0, $this->repo->getUsedFiles('commonswiki_p', [74025845]));
    }
}
