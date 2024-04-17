<?php declare( strict_types=1 );

namespace App\Tests\Repository;

use App\Repository\EventRepository;
use App\Tests\EventMetricsTestCase;

/**
 * @covers \App\Repository\Repository
 * @group replicas
 */
class RepositoryTest extends EventMetricsTestCase {

	/**
	 * @dataProvider provideGetActorIdsFromUsernames
	 * @param string[] $input
	 * @param int[] $expected
	 */
	public function testGetActorIdsFromUsernames( array $input, array $expected ): void {
		$repo = static::getContainer()->get( EventRepository::class );
		$ids = $repo->getActorIdsFromUsernames( 'enwiki_p', $input );

		$this->assertEqualsCanonicalizing( $expected, $ids );
	}

	/**
	 * @return array[]
	 */
	public function provideGetActorIdsFromUsernames(): array {
		return [
			[ [], [] ],
			[ [ '<nonexistent>' ], [] ],
			[ [ 'MaxSem', 'MusikAnimal', 'Samwilson', '<some other nonexistent>' ], [ 26503, 210966, 7528 ] ],
		];
	}
}
