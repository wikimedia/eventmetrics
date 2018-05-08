<?php
/**
 * This file contains only the EventWikiTest class.
 */

namespace Tests\AppBundle\Model;

use PHPUnit_Framework_TestCase;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Program;
use AppBundle\Model\Event;
use AppBundle\Model\EventWikiStat;
use AppBundle\Model\Organizer;
use InvalidArgumentException;

/**
 * Tests for the EventWiki class.
 */
class EventWikiTest extends PHPUnit_Framework_TestCase
{
    /** @var Event The Event that the EventWiki is part of. */
    protected $event;

    public function setUp()
    {
        date_default_timezone_set('UTC');

        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $this->event = new Event(
            $program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );
    }

    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor()
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);

        $wiki = new EventWiki($this->event, 'test.wikipedia');

        // Basic getters.
        $this->assertEquals($this->event, $wiki->getEvent());
        $this->assertEquals('test.wikipedia', $wiki->getDomain());

        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $wiki->getStatistics()
        );

        // Make sure the association was made on the Event object, too.
        $this->assertEquals($wiki, $this->event->getWikis()[0]);
    }

    /**
     * Test adding and removing statistics.
     */
    public function testAddRemoveStatistics()
    {
        $wiki = new EventWiki($this->event, 'test.wikipedia');

        $this->assertEquals(0, count($wiki->getStatistics()));

        // Add an EventWikiStat.
        $ews = new EventWikiStat($wiki, 'pages-improved', 50);
        $wiki->addStatistic($ews);

        $this->assertEquals($ews, $wiki->getStatistics()[0]);
        $this->assertEquals($ews, $wiki->getStatistic('pages-improved'));

        // Try adding the same one, which shouldn't duplicate.
        $wiki->addStatistic($ews);
        $this->assertEquals(1, count($wiki->getStatistics()));

        // Removing the statistic.
        $wiki->removeStatistic($ews);
        $this->assertEquals(0, count($wiki->getStatistics()));

        // Double-remove shouldn't error out.
        $wiki->removeStatistic($ews);

        // Clearing statistics.
        $wiki->addStatistic($ews);
        $this->assertEquals(1, $wiki->getStatistics()->count());
        $wiki->clearStatistics();
        $this->assertEquals(0, $wiki->getStatistics()->count());
    }

    /**
     * Test methods involving wiki families.
     */
    public function testWikiFamilies()
    {
        // Not a wiki family.
        $wiki = new EventWiki($this->event, 'test.wikipedia');
        $this->assertFalse($wiki->isFamilyWiki());
        $this->assertEquals('wikipedia', $wiki->getFamilyName());
        $this->assertEquals([], $wiki->getChildWikis());
        $this->assertFalse($wiki->isChildWiki());

        // Create a *.wikipedia EventWiki, making the above EventWiki a child.
        $wikiFam = new EventWiki($this->event, '*.wikipedia');
        $this->assertTrue($wikiFam->isFamilyWiki());
        $this->assertEquals('wikipedia', $wikiFam->getFamilyName());
        $this->assertEquals([$wiki], $wikiFam->getChildWikis()->toArray());
        $this->assertTrue($wiki->isChildWiki());
    }
}
