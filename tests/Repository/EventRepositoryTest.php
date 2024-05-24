<?php declare( strict_types=1 );

namespace App\Tests\Repository;

use App\Repository\EventRepository;
use App\Tests\EventMetricsTestCase;

/**
 * @covers \App\Repository\EventRepository
 * @group replicas
 */
class EventRepositoryTest extends EventMetricsTestCase {
	/** @var EventRepository */
	private EventRepository $repo;

	public function setUp(): void {
		parent::setUp();
		$this->repo = static::getContainer()->get( EventRepository::class );
	}

	/**
	 * @covers \App\Repository\EventRepository::getPagesUsingFile()
	 */
	public function testGetPagesUsingFile(): void {
		static::assertGreaterThan( 0, $this->repo->getPagesUsingFile( 'commonswiki_p', 'Ultrasonic_humidifier.jpg' ) );
		static::assertGreaterThan( 0, $this->repo->getPagesUsingFile( 'enwiki_p', '2-cube.png' ) );
	}

	/**
	 * @covers \App\Repository\EventRepository::getUsedFiles()
	 */
	public function testGetUsedFiles(): void {
		// 74025845 = [[File:XTools service overloaded error page.png]] (should never be in mainspace).
		static::assertSame( 0, $this->repo->getUsedFiles( 'commonswiki_p', [ 74025845 ] ) );
	}
}
