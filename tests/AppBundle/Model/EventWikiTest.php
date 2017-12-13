<?php
/**
 * This file contains only the EventWikiTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Program;
use AppBundle\Model\Event;
use AppBundle\Model\Organizer;
use InvalidArgumentException;

/**
 * Tests for the EventWiki class.
 */
class EventWikiTest extends PHPUnit_Framework_TestCase
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
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $wiki = new EventWiki($event, 'test.wikipedia');

        // Basic getters.
        $this->assertEquals($event, $wiki->getEvent());
        $this->assertEquals('test.wikipedia', $wiki->getDomain());

        // Make sure the association was made on the Event object, too.
        $this->assertEquals($wiki, $event->getWikis()[0]);
    }
}
