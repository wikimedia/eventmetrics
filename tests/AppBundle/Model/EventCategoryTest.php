<?php
/**
 * This file contains only the EventCategory class.
 */

namespace Tests\AppBundle\Model;

use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Class EventCategoryTest
 * @package Tests\AppBundle\Model
 */
class EventCategoryTest extends GrantMetricsTestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $event = new Event(
            $program,
            'My event',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );
        $wiki = new EventWiki($event, 'test.wikipedia');

        static::assertFalse($event->hasCategories());

        $eventCategory = new EventCategory($wiki, 500);

        static::assertTrue($event->hasCategories());
        static::assertEquals(1, $event->getNumCategories());

        // Getters.
        static::assertEquals($event, $eventCategory->getEvent());
        static::assertEquals($wiki, $eventCategory->getWiki());
    }
}
