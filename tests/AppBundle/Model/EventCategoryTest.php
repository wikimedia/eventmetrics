<?php
/**
 * This file contains only the EventCategory class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * Class EventCategoryTest
 */
class EventCategoryTest extends EventMetricsTestCase
{
    /** @var Event The Event that the EventCategory belongs to. */
    protected $event;

    public function setUp(): void
    {
        parent::setUp();

        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $this->event = new Event(
            $program,
            'My event',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );
    }

    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor(): void
    {
        $eventCategory = new EventCategory($this->event, ' Foo_bar ', 'test.wikipedia');

        static::assertCount(1, $this->event->getCategories());
        static::assertEquals(1, $this->event->getNumCategories());

        // Getters.
        static::assertEquals($this->event, $eventCategory->getEvent());
        static::assertEquals('Foo bar', $eventCategory->getTitle());
    }

    /**
     * Test adding and removing categories.
     */
    public function testAddRemoveCategories(): void
    {
        static::assertEquals(0, count($this->event->getCategories()));

        // Add an EventCategory.
        $cat = new EventCategory($this->event, 'Foo bar', 'test.wikipedia');

        static::assertEquals($cat, $this->event->getCategories()[0]);

        // Manually set the Event, which should make no difference.
        $cat->setEvent($this->event);
        static::assertEquals($cat, $this->event->getCategories()[0]);

        // Try adding the same one, which shouldn't duplicate.
        $this->event->addCategory($cat);
        static::assertEquals(1, count($this->event->getCategories()));

        // Getting titles, going by associated wiki.
        $wiki = new EventWiki($this->event, 'test.wikipedia');
        static::assertEquals(['Foo_bar'], $this->event->getCategoryTitlesForWiki($wiki));

        // Removing the category.
        $this->event->removeCategory($cat);
        static::assertEquals(0, count($this->event->getCategories()));

        // Double-remove shouldn't error out.
        $this->event->removeCategory($cat);

        // Clearing categories.
        $this->event->addCategory($cat);
        static::assertEquals(1, $this->event->getCategories()->count());
        $this->event->clearCategories();
        static::assertEquals(0, $this->event->getCategories()->count());
    }
}
