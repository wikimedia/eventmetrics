<?php

declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Repository\EventWikiRepository;
use DateTime;
use Tests\AppBundle\EventMetricsTestCase;

class EventWikiRepositoryTest extends EventMetricsTestCase
{

    /**
     * @covers \AppBundle\Repository\EventWikiRepository::getPageIds()
     */
    public function testGetPageIds():void
    {
        $kernel = static::bootKernel();
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $repo = new EventWikiRepository($entityManager);
        $repo->setContainer($kernel->getContainer());
        $dbName = $repo->getDbNameFromDomain('en.wikipedia');
        $from = new DateTime('2003-11-16 13:15');
        $to = new DateTime('2003-11-16 15:19');
        $users = ['Someone else'];
        $allPagesExpected     = [2112961, 368673];
        $pagesCreatedExpected = [         368673];
        $pagesEditedExpected  = [2112961        ];
        // All pages.
        $allPagesActual = $repo->getPageIds($dbName, $from, $to, $users, []);
        static::assertEquals($allPagesExpected, $allPagesActual);
        // Pages created.
        $pagesCreatedActual = $repo->getPageIds($dbName, $from, $to, $users, [], 'created');
        static::assertEquals($pagesCreatedExpected, $pagesCreatedActual);
        // Pages edited.
        $pagesEditedActual = $repo->getPageIds($dbName, $from, $to, $users, [], 'edited');
        static::assertEquals($pagesEditedExpected, $pagesEditedActual);
    }
}
