<?php
/**
 * This file contains only the EventStatTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\EventStat;
use AppBundle\Model\Program;
use AppBundle\Model\Event;
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
        $program = new Program('Test program');
        $event = new Event(
            $program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );

        $eventStat = new EventStat($event, 'retention', 50);

        // Getters.
        $this->assertEquals($event, $eventStat->getEvent());
        $this->assertEquals('retention', $eventStat->getMetric());
        $this->assertEquals(50, $eventStat->getValue());

        // Make sure the association was made on the Event object, too.
        $this->assertEquals($eventStat, $event->getStatistics()[0]);

        // Invalid metric.
        $this->expectException(InvalidArgumentException::class);
        $eventStat = new EventStat($event, 'invalid', 30);
    }
}
