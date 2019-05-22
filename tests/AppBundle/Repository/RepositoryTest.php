<?php
declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Repository\EventRepository;
use AppBundle\Repository\Repository;
use Doctrine\ORM\EntityManager;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * @covers \AppBundle\Repository\Repository
 */
class RepositoryTest extends EventMetricsTestCase
{
    private function getRepository(): Repository
    {
        $kernel = static::bootKernel();
        /** @var EntityManager $entityManager */
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        // Have to use a child class because Repository is abstract
        $repo = new EventRepository($entityManager);
        $repo->setContainer($kernel->getContainer());

        return $repo;
    }

    /**
     * @dataProvider provideGetActorIdsFromUsernames
     * @param string[] $input
     * @param int[] $expected
     */
    public function testGetActorIdsFromUsernames(array $input, array $expected): void
    {
        $repo = $this->getRepository();
        $ids = $repo->getActorIdsFromUsernames('enwiki_p', $input);

        $this->assertEqualsCanonicalizing($expected, $ids);
    }

    /**
     * @return mixed[][]
     */
    public function provideGetActorIdsFromUsernames(): array
    {
        return [
            [ [], [] ],
            [ ['<nonexistent>'], []],
            [ ['MaxSem', 'MusikAnimal', 'Samwilson', '<some other nonexistent>'], [26503, 210966, 7528]],
        ];
    }
}
