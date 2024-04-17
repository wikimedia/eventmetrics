<?php declare( strict_types=1 );

namespace App\Tests\Repository;

use App\Repository\EventWikiRepository;
use App\Tests\EventMetricsTestCase;
use DateTime;

/**
 * Tests for EventWikiRepository.
 * @group replicas
 */
class EventWikiRepositoryTest extends EventMetricsTestCase {
	/** @var EventWikiRepository */
	private EventWikiRepository $repo;

	public function setUp(): void {
		parent::setUp();
		$this->repo = static::getContainer()->get( EventWikiRepository::class );
	}

	/**
	 * Further coverage in ProcessEventCommandTest.
	 * @covers \App\Repository\EventWikiRepository::getPageIds()
	 */
	public function testGetPageIds(): void {
		$dbName = $this->repo->getDbNameFromDomain( 'en.wikipedia' );
		$from = new DateTime( '2018-06-09 04:00' );
		$to = new DateTime( '2018-06-12 03:59' );
		$users = [ 'MusikAnimal', 'Jon Kolbert' ];
		$actors = $this->repo->getActorIdsFromUsernames( $dbName, $users );
		// [[Domino Park]], [[Spring Creek Park]]
		$allPagesExpected = [ 57645508, 55751986 ];
		// [[Domino Park]]
		$pagesCreatedExpected = [ 57645508 ];
		// All pages.
		$allPagesActual = $this->repo->getPageIds( $dbName, $from, $to, $actors, [ 'Parks_in_Brooklyn' ] );
		static::assertEquals( $allPagesExpected, $allPagesActual );
		// Pages created.
		$pagesCreatedActual = $this->repo->getPageIds(
			$dbName, $from, $to, $actors,
			[ 'Parks_in_Brooklyn' ], 'created'
		);
		static::assertEquals( $pagesCreatedExpected, $pagesCreatedActual );
	}
}
