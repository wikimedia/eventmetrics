<?php declare( strict_types=1 );

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class EventMetricsTestCase
 */
class EventMetricsTestCase extends WebTestCase {
	public function setUp(): void {
		date_default_timezone_set( 'UTC' );
	}
}
