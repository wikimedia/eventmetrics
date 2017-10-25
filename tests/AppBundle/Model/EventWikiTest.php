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

        $eventStat = new EventWiki($event, 'testwiki_p');

        $this->assertEquals($event, $eventStat->getEvent());
        $this->assertEquals('testwiki_p', $eventStat->getDbName());
    }
}
