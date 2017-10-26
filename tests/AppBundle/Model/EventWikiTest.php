<?php
/**
 * This file contains only the EventWikiTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Program;
use AppBundle\Model\Event;
use InvalidArgumentException;

/**
 * Tests for the EventStat class.
 */
class EventWikiTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $program = new Program('Test program');
        $event = new Event(
            $program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $wiki = new EventWiki($event, 'testwiki_p');

        // Basic getters.
        $this->assertEquals($event, $wiki->getEvent());
        $this->assertEquals('testwiki_p', $wiki->getDbName());

        // Make sure the association was made on the Event object, too.
        $this->assertEquals($wiki, $event->getWikis()[0]);
    }
}
