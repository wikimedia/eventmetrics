<?php
/**
 * This file contains only the OrganizerTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Organizer;

/**
 * Tests for the Organizer class.
 */
class OrganizerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        $this->assertEquals(50, $organizer->getUserId());
    }
}
