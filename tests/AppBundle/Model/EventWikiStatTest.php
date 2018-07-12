<?php
/**
 * This file contains only the EventWikiStatTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use AppBundle\Model\EventWikiStat;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use InvalidArgumentException;

/**
 * Tests for the EventWikiStat class.
 */
class EventWikiStatTest extends PHPUnit_Framework_TestCase
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
        $eventWiki = new EventWiki($event);

        $ews = new EventWikiStat($eventWiki, 'pages-created', 50);

        // Getters.
        static::assertEquals($event, $ews->getEvent());
        static::assertEquals($eventWiki, $ews->getWiki());
        static::assertEquals('pages-created', $ews->getMetric());
        static::assertEquals(50, $ews->getValue());
        static::assertEquals(null, $ews->getOffset());

        // Make sure the association was made on the Event object, too.
        static::assertEquals($ews, $eventWiki->getStatistics()[0]);

        // Invalid metric.
        $this->expectException(InvalidArgumentException::class);
        new EventWikiStat($eventWiki, 'invalid', 30);
    }
}
