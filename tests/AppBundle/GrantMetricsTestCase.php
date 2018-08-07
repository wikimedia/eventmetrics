<?php
/**
 * This file contains only the GrantMetricsTestCase class.
 */

namespace Tests\AppBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class GrantMetricsTestCase
 * @package Tests\AppBundle
 */
class GrantMetricsTestCase extends WebTestCase
{
    public function setUp()
    {
        date_default_timezone_set('UTC');
    }
}
