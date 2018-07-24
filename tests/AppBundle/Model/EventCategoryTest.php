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

use PHPUnit_Framework_TestCase;

class EventCategoryTest extends PHPUnit_Framework_TestCase
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

        $eventCategory = new EventCategory($wiki, 500);

        // Getters.
        static::assertEquals($event, $eventCategory->getEvent());
        static::assertEquals($wiki, $eventCategory->getWiki());
    }
}
