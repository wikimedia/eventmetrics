<?php
/**
 * This file contains only the EventTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Job;
use AppBundle\Model\Organizer;
use AppBundle\Model\Participant;
use AppBundle\Model\Program;
use DateTime;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * Tests for the Event class.
 */
class EventTest extends EventMetricsTestCase
{
    /** @var Program The Program that the Event is part of. */
    protected $program;

    public function setUp(): void
    {
        parent::setUp();

        $organizer = new Organizer(50);
        $this->program = new Program($organizer);
    }

    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01 12:00',
            new DateTime('2017-03-01 16:00'),
            'America/New_York'
        );
        static::assertNull($event->getId());

        static::assertEquals($this->program, $event->getProgram());
        static::assertEquals('My_program', $event->getTitle());
        static::assertEquals('My program', $event->getDisplayTitle());
        static::assertEquals('America/New_York', $event->getTimezone());
        static::assertEquals('America/New York', $event->getDisplayTimezone());

        static::assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $event->getParticipants()
        );
        static::assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $event->getStatistics()
        );
        static::assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $event->getWikis()
        );
    }

    /**
     * Tests that dates in the constructor were stored properly in the class.
     */
    public function testDates(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01 16:00',
            new DateTime('2017-03-01 21:00'),
            'America/New_York'
        );
        static::assertEquals(new DateTime('2017-01-01 16:00'), $event->getStart());
        static::assertEquals(new DateTime('2017-03-01 21:00'), $event->getEnd());
        static::assertEquals(new DateTime('2017-01-01 21:00'), $event->getStartUTC());
        static::assertEquals(new DateTime('2017-03-02 02:00'), $event->getEndUTC());

        // Date types reversed.
        $event2 = new Event(
            $this->program,
            'My program',
            new DateTime('2017-03-01'),
            '2017-04-01'
        );
        static::assertEquals(new DateTime('2017-03-01'), $event2->getStart());
        static::assertEquals(new DateTime('2017-04-01'), $event2->getEnd());
    }

    /**
     * Test adding and removing statistics.
     */
    public function testAddRemoveStatistics(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new DateTime('2017-03-01'),
            'America/New_York'
        );
        static::assertEquals(0, count($event->getStatistics()));

        // Add an EventStat.
        $eventStat = new EventStat($event, 'retention', 50);
        $event->addStatistic($eventStat);
        static::assertEquals($eventStat, $event->getStatistics()[0]);
        static::assertEquals($eventStat, $event->getStatistic('retention'));

        // Try adding the same one, which shouldn't duplicate.
        $event->addStatistic($eventStat);
        static::assertEquals(1, count($event->getStatistics()));

        // Removing the statistic.
        $event->removeStatistic($eventStat);
        static::assertEquals(0, count($event->getStatistics()));

        // Double-remove shouldn't error out.
        $event->removeStatistic($eventStat);

        // Changing the 'updated' attribute.
        $datetime = new DateTime('2017-01-01');
        $event->setUpdated($datetime);
        static::assertEquals($datetime, $event->getUpdated());

        // Clearing all statistics.
        $event->addStatistic($eventStat);
        static::assertEquals(1, count($event->getStatistics()));
        $event->clearStatistics();
        static::assertEquals(0, count($event->getStatistics()));
        static::assertNull($event->getUpdated());
    }

    /**
     * Test adding and removing participants.
     */
    public function testAddRemoveParticipant(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new DateTime('2017-03-01'),
            'America/New_York'
        );

        static::assertEquals(0, count($event->getParticipants()));

        // Add a Participant.
        $participant = new Participant($event, 50);
        $participant->setUsername('DannyH (WMF)');
        $event->addParticipant($participant);

        static::assertEquals($participant, $event->getParticipants()[0]);
        static::assertEquals(['DannyH (WMF)'], $event->getParticipantNames());

        // Try adding the same one, which shouldn't duplicate.
        $event->addParticipant($participant);
        static::assertEquals(1, $event->getNumParticipants());

        // Removing the participant.
        $event->removeParticipant($participant);
        static::assertEquals(0, $event->getNumParticipants());

        // Double-remove shouldn't error out.
        $event->removeParticipant($participant);

        // One more time to test clearParticipants method.
        $event->addParticipant($participant);
        static::assertEquals(1, $event->getNumParticipants());
        $event->clearParticipants();
        static::assertEquals(0, $event->getNumParticipants());
    }

    /**
     * Test adding and removing wikis.
     */
    public function testAddRemoveWiki(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new DateTime('2017-03-01'),
            'America/New_York'
        );

        static::assertEquals(0, count($event->getParticipants()));

        // Add a wiki.
        $wiki = new EventWiki($event, 'test.wikipedia');
        $event->addWiki($wiki);

        static::assertEquals($wiki, $event->getWikis()[0]);
        static::assertEquals($wiki, $event->getWikiByDomain('test.wikipedia'));

        // Try adding the same one, which shouldn't duplicate.
        $event->addWiki($wiki);
        static::assertEquals(1, count($event->getWikis()));

        // Removing the wiki.
        $event->removeWiki($wiki);
        static::assertEquals(0, count($event->getWikis()));

        // Double-remove shouldn't error out.
        $event->removeWiki($wiki);
    }

    /**
     * Tests the validators on the model.
     */
    public function testValidations(): void
    {
        self::bootKernel();
        $validator = static::$kernel->getContainer()->get('validator');

        $organizer = new Organizer('MusikAnimal');
        $organizer->setUserId(50);
        $program = new Program($organizer);
        $event = new Event($program);

        $event->setTitle('Foo/bar');
        $errors = $validator->validate($event);
        static::assertEquals(
            'error-title-invalid-chars',
            $errors->get(0)->getMessage()
        );

        $event->setTitle('123');
        $errors = $validator->validate($event);
        static::assertEquals(
            'error-title-numeric',
            $errors->get(0)->getMessage()
        );
    }

    /**
     * Validations of the Event itself.
     */
    public function testIsValid(): void
    {
        $event = new Event($this->program, 'Test event');

        static::assertFalse($event->isValid());

        // Add start/end dates.
        $event->setStart('2018-01-01');
        $event->setEnd('2018-02-01');
        static::assertFalse($event->isValid());

        // Set participants.
        new Participant($event, 50);
        static::assertFalse($event->isValid());

        // Set wikis.
        new EventWiki($event, 'test.wikipedia');
        static::assertTrue($event->isValid());
    }

    /**
     * Jobs associated with the Event.
     */
    public function testJobs(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new DateTime('2017-03-01'),
            'America/New_York'
        );
        $job = new Job($event);

        static::assertTrue($event->hasJob());
        static::assertEquals(1, $event->getNumJobs());
        static::assertEquals($job, $event->getJobs()[0]);

        // Set the submitted timestamp, normally called when persisting the Job.
        $job->setSubmitted();
        // The Job hence should not be 'stale' because we just created it.
        static::assertNotContains($job, $event->getStaleJobs());
        // The Job is stale if we use an offset of 0 seconds.
        static::assertContains($job, $event->getStaleJobs('-0 seconds'));

        $event->clearJobs();
        static::assertFalse($event->hasJob());
        static::assertEquals(0, $event->getNumJobs());

        // Add again and remove via object.
        $event->addJob($job);
        static::assertEquals(1, $event->getNumJobs());
        $event->removeJob($job);
        // Removing twice should not error out.
        $event->removeJob($job);
        static::assertEquals(0, $event->getNumJobs());
    }

    /**
     * Changing the 'updated' attribute.
     */
    public function testUpdatedAt(): void
    {
        $event = new Event(
            $this->program,
            '  My program  ',
            '2017-01-01',
            new DateTime('2017-03-01'),
            'America/New_York'
        );
        $datetime = new DateTime('2017-01-01 17:00');
        $event->setUpdated($datetime);
        static::assertEquals($datetime, $event->getUpdated());
        static::assertEquals(
            new DateTime('2017-01-01 12:00'),
            $event->getUpdatedUTC()
        );
    }

    /**
     * Test methods involving EventWikis that represent a family.
     */
    public function testWikiFamilies(): void
    {
        $event = new Event($this->program);

        $family = new EventWiki($event, '*.wikipedia');
        $child = new EventWiki($event, 'test.wikipedia');
        $orphan = new EventWiki($event, 'fr.wiktionary');

        static::assertEquals([$family], $event->getFamilyWikis()->toArray());

        // Doctrine doesn't reindex the arrays (instead preserving original keys),
        // so we need to use array_values in our test.
        static::assertEquals([$orphan], array_values($event->getOrphanWikis()->toArray()));

        static::assertEquals(
            [$family, $orphan],
            array_values($event->getOrphanWikisAndFamilies()->toArray())
        );

        static::assertEquals([$child], array_values($event->getChildWikis()->toArray()));
        $event->clearChildWikis();
        static::assertEquals(0, $event->getChildWikis()->count());

        // Family EventWiki should still be there.
        static::assertEquals([$family], $event->getFamilyWikis()->toArray());

        // Statistics available based on associated families.
        static::assertEquals(
            [
                'participants' => null,
                'new-editors' => 15,
                'retention' => 7,
                'edits' => null,
                'byte-difference' => null,
                'pages-created' => null,
                'pages-improved' => null,
                'pages-created-pageviews' => null,
                'pages-improved-pageviews-avg' => 30,
                'files-uploaded' => null,
                'file-usage' => null,
                'pages-using-files' => null,
                'pages-using-files-pageviews-avg' => 30,
            ],
            $event->getAvailableMetrics()
        );
    }

    /**
     * @covers \AppBundle\Model\Event::getWikisByFamily()
     */
    public function testWikisByFamily(): void
    {
        $event = new Event($this->program);

        new EventWiki($event, '*.wikipedia');
        $testwiki = new EventWiki($event, 'test.wikipedia');
        $commons = new EventWiki($event, 'commons.wikimedia');

        static::assertEquals([
            'commons' => [$commons],
            'wikipedia' => [$testwiki],
        ], $event->getWikisByFamily());
    }

    /**
     * @covers \AppBundle\Model\Event::getWikisWithoutCategories()
     */
    public function testGetWikisWithoutCategories(): void
    {
        $event = new Event($this->program);

        // Initially has no wikis.
        static::assertCount(0, $event->getWikisWithoutCategories());

        // Add a non-Wikidata wiki.
        $event->addWiki(new EventWiki($event, 'fr.wikipedia'));
        static::assertCount(1, $event->getWikisWithoutCategories());

        // Add Wikidata, and the count shouldn't change.
        $event->addWiki(new EventWiki($event, 'www.wikidata'));
        static::assertCount(1, $event->getWikisWithoutCategories());

        // Add another non-Wikidata wiki, and the count should increase.
        $event->addWiki(new EventWiki($event, 'commons.wikimedia'));
        $wikisWithoutCategories = $event->getWikisWithoutCategories();
        static::assertCount(2, $wikisWithoutCategories);
        // Also check the specific wikis included.
        static::assertEquals('fr.wikipedia', $wikisWithoutCategories->get(0)->getDomain());
        static::assertEquals('commons.wikimedia', $wikisWithoutCategories->get(2)->getDomain());
    }
}
