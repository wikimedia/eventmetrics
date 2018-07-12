<?php
/**
 * This file contains only the EventStatTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use InvalidArgumentException;

/**
 * Tests for the EventStat class.
 */
class EventStatTest extends PHPUnit_Framework_TestCase
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

        $eventStat = new EventStat($event, 'retention', 50, 10);

        // Getters.
        static::assertEquals($event, $eventStat->getEvent());
        static::assertEquals('retention', $eventStat->getMetric());
        static::assertEquals(50, $eventStat->getValue());
        static::assertEquals(10, $eventStat->getOffset());

        // Make sure the association was made on the Event object, too.
        static::assertEquals($eventStat, $event->getStatistics()[0]);

        // Invalid metric.
        $this->expectException(InvalidArgumentException::class);
        new EventStat($event, 'invalid', 30);
    }
}
