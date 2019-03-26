<?php

declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Repository\EventWikiRepository;
use DateTime;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * Tests for EventWikiRepository.
 */
class EventWikiRepositoryTest extends EventMetricsTestCase
{
    /** @var EventWikiRepository $repo */
    private $repo;

    public function setUp(): void
    {
        parent::setUp();

        $kernel = static::bootKernel();

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $this->repo = new EventWikiRepository($entityManager);
        $this->repo->setContainer($kernel->getContainer());
    }

    /**
     * Further coverage in ProcessEventCommandTest.
     * @covers \AppBundle\Repository\EventWikiRepository::getPageIds()
     */
    public function testGetPageIds(): void
    {
        $dbName = $this->repo->getDbNameFromDomain('en.wikipedia');
        $from = new DateTime('2018-06-09 04:00');
        $to = new DateTime('2018-06-12 03:59');
        $users = ['MusikAnimal', 'Jon Kolbert'];
        $allPagesExpected     = [57645508, 55751986]; // [[Domino Park]], [[Spring Creek Park]]
        $pagesCreatedExpected = [57645508          ]; // [[Domino Park]]
        // All pages.
        $allPagesActual = $this->repo->getPageIds($dbName, $from, $to, $users, ['Parks_in_Brooklyn']);
        static::assertEquals($allPagesExpected, $allPagesActual);
        // Pages created.
        $pagesCreatedActual = $this->repo->getPageIds($dbName, $from, $to, $users, ['Parks_in_Brooklyn'], 'created');
        static::assertEquals($pagesCreatedExpected, $pagesCreatedActual);
    }
}
