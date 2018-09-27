<?php
/**
 * This file contains only the GrantMetricsTestCase class.
 */

declare(strict_types=1);

namespace Tests\AppBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class GrantMetricsTestCase
 */
class GrantMetricsTestCase extends WebTestCase
{
    public function setUp(): void
    {
        date_default_timezone_set('UTC');
    }
}
