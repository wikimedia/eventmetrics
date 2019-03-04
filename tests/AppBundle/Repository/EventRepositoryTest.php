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

    /**
     * Ensures we only count unique pages that are using the files.
     * For this example we use an old abandoned account with numerous files used on the same page.
     * @see https://commons.wikimedia.org/wiki/Special:Contributions/Krupin.1
     */
    public function testGetPagesUsingFiles(): void
    {
        $ret = $this->repo->getPagesUsingFiles(
            'commonswiki_p',
            new \DateTime('2014-12-01'),
            new \DateTime('2014-12-02'),
            ['Krupin.1']
        );

        static::assertCount(1, $ret);
    }
}
