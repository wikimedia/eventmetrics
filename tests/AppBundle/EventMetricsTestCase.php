<?php
/**
 * This file contains only the EventMetricsTestCase class.
 */

declare(strict_types=1);

namespace Tests\AppBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class EventMetricsTestCase
 */
class EventMetricsTestCase extends WebTestCase
{
    public function setUp(): void
    {
        date_default_timezone_set('UTC');
    }
}
