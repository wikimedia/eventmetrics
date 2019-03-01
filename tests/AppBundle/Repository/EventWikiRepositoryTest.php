<?php

declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Repository\EventWikiRepository;
use AppBundle\Repository\PageviewsRepository;
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
     * @covers \AppBundle\Repository\EventWikiRepository::getPageIds()
     */
    public function testGetPageIds():void
    {
        $dbName = $this->repo->getDbNameFromDomain('en.wikipedia');
        $from = new DateTime('2003-11-16 13:15');
        $to = new DateTime('2003-11-16 15:19');
        $users = ['Someone else'];
        $allPagesExpected     = [2112961, 368673];
        $pagesCreatedExpected = [         368673];
        $pagesEditedExpected  = [2112961        ];
        // All pages.
        $allPagesActual = $this->repo->getPageIds($dbName, $from, $to, $users, []);
        static::assertEquals($allPagesExpected, $allPagesActual);
        // Pages created.
        $pagesCreatedActual = $this->repo->getPageIds($dbName, $from, $to, $users, [], 'created');
        static::assertEquals($pagesCreatedExpected, $pagesCreatedActual);
        // Pages edited.
        $pagesEditedActual = $this->repo->getPageIds($dbName, $from, $to, $users, [], 'edited');
        static::assertEquals($pagesEditedExpected, $pagesEditedActual);
    }

    /**
     * @covers \AppBundle\Repository\EventWikiRepository::getPageviews()
     */
    public function testPageviews(): void
    {
        $pvRepo = new PageviewsRepository();

        $start = new DateTime('2018-06-06');
        $end = new DateTime('2018-06-12');

        // Raw total pageviews.
        static::assertEquals(
            361,
            $this->repo->getPageviewsPerArticle($pvRepo, 'en.wikipedia', 'Domino_Park', $start, $end)
        );

        [$total, $avg] = $this->repo->getPageviewsPerArticle(
            $pvRepo,
            'en.wikipedia',
            'Domino_Park',
            $start,
            $end,
            true
        );

        // First element should be the same as total pageviews.
        static::assertEquals(361, $total);

        // Average during the period, which should only apply to the days the article existed (June 10 - June 12).
        static::assertEquals(120, $avg);
    }
}
