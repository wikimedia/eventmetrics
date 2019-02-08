<?php
/**
 * This file contains only the EventControllerTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Program;
use DateTime;

/**
 * Integration/functional tests for the EventController.
 */
class EventControllerTest extends DatabaseAwareWebTestCase
{
    /** @var int ID of the Program, used for routing. */
    private $programId;

    /** @var int ID of the Event, used for routing. */
    private $eventId;

    /**
     * Called before each test.
     */
    public function setUp(): void
    {
        parent::setUp();

        // This tests runs code that throws exceptions, and we don't want that in the test output.
        $this->suppressErrors();
    }

    /**
     * No index of events without program in URL, just redirects to programs page.
     */
    public function testIndex(): void
    {
        $this->loginUser('Test user');
        $this->crawler = $this->client->request('GET', '/events');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());
    }

    /**
     * Test that ID/title routes both work. We cannot use a data provider here because we need to programmatically
     * get the event and program IDs.
     */
    public function testRouting(): void
    {
        $this->loginUser();

        // Load extended fixtures.
        $this->addFixture(new LoadFixtures('extended'));
        $this->executeFixtures();

        /** @var Event $event */
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);
        $eventId = $event->getId();
        $programId = $event->getProgram()->getId();

        $validRoutes = [
            // Historical routes that should still work.
            "/programs/My_fun_program",
            "/programs/My_fun_program/",
            "/programs/My_fun_program/Oliver_and_Company",
            "/programs/My_fun_program/Oliver_and_Company/",

            // Historical routes except with IDs.
            "/programs/$programId/$eventId",

            // New routes.
            "/programs/$programId",
            "/programs/$programId/",
            "/programs/$programId/edit",
            "/programs/$programId/events/$eventId",
            "/programs/$programId/events/$eventId/edit",
        ];

        $this->client->followRedirects();
        foreach ($validRoutes as $route) {
            $this->client->request('GET', $route);
            static::assertTrue($this->client->getResponse()->isSuccessful(), "Not found: $route");
        }
    }

    /**
     * Workflow, including creating, updating and deleting events.
     */
    public function testWorkflow(): void
    {
        // Load basic fixtures.
        $this->addFixture(new LoadFixtures());
        $this->executeFixtures();

        /** @var Program $program */
        $program = $this->entityManager
            ->getRepository('Model:Program')
            ->findOneBy(['title' => 'My_fun_program']);
        $this->programId = $program->getId();

        $this->loginUser();

        $this->indexSpec();
        $this->newSpec();
        $this->createSpec();
        $this->validateSpec();
        $this->updateSpec();
        $this->familyWikiSpec();
        $this->showSpec();
        $this->participantsSpec();
        $this->categoriesSpec();
        $this->cloneSpec();
        $this->deleteSpec();
    }

    /**
     * Attempting to browse to /programs when not logged in at all.
     */
    public function testLoggedOut(): void
    {
        $this->crawler = $this->client->request('GET', '/programs');
        $this->response = $this->client->getResponse();
        static::assertTrue($this->response->isRedirect());

        $this->crawler = $this->client->followRedirect();
        static::assertContains(
            'Please login to continue',
            $this->crawler->filter('.splash-dialog')->text()
        );
    }

    /**
     * Test while logged in as a non-organizer, ensuring edit options aren't available.
     */
    public function testNonOrganizer(): void
    {
        // Load more test events.
        $this->addFixture(new LoadFixtures('extended'));
        $this->executeFixtures();

        $this->loginUser('Not an organizer');

        /** @var Event $event */
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);
        $eventId = $event->getId();
        $programId = $event->getProgram()->getId();

        $this->crawler = $this->client->request('GET', "/programs/$programId/events/$eventId");
        $this->response = $this->client->getResponse();
        static::assertEquals(403, $this->response->getStatusCode());

        /**
         * For now, you must be an organizer of an event in order to view it.
         */

        // // Should see the 'edit event', since we are logged in and are one of the organizers.
        // static::assertNotContains(
        //     'edit event',
        //     $this->crawler->filter('.page-header')->text()
        // );

        // // Should not be able to edit an event.
        // $this->crawler = $this->client->request('GET', '/programs/'.$this->programId.'/'.$this->eventId.'/edit');
        // static::assertEquals(403, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Index of events for the program.
     */
    private function indexSpec(): void
    {
        // 'My_fun_program' was already created via fixtures.
        $this->crawler = $this->client->request('GET', $this->getProgramUrl());
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
    }

    /**
     * Form to create a new event.
     */
    private function newSpec(): void
    {
        $this->crawler = $this->client->request('GET', $this->getProgramUrl().'/events/new');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertContains(
            'Create a new event',
            $this->crawler->filter('.page-header')->text()
        );
    }

    /**
     * Creating a new event.
     */
    private function createSpec(): void
    {
        $form = $this->crawler->selectButton('Save')->form();
        $form['event[title]'] = ' The Lion King ';
        $form['event[wikis][0]'] = 'dewiki';
        $form['event[start]'] = '2017-01-01 18:00:00-00:00';
        $form['event[end]'] = '2017-02-01 21:00:00-00:00';
        $form['event[timezone]'] = 'America/New_York';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        /** @var Event $event */
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'The_Lion_King']);

        // Used throughout the rest of the specs.
        $this->eventId = $event->getId();

        static::assertNotNull($event);
        static::assertEquals(
            'My_fun_program',
            $event->getProgram()->getTitle()
        );
        static::assertEquals(
            new DateTime('2017-01-01 18:00'),
            $event->getStart()
        );

        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['event' => $event]);
        static::assertNotNull($eventWiki);
        static::assertEquals(
            'de.wikipedia',
            $eventWiki->getDomain()
        );

        $this->crawler = $this->client->followRedirect();

        static::assertContains(
            'The Lion King',
            $this->crawler->filter('.events-list')->text()
        );

        // Should be deletable.
        static::assertNotContains(
            'disabled',
            $this->crawler->filter('.event-action__delete')->attr('class')
        );
    }

    /**
     * Updating an event.
     */
    private function updateSpec(): void
    {
        $this->crawler = $this->client->request('GET', $this->getEventUrl().'/edit');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $this->crawler->selectButton('Save')->form();

        $form['event[title]'] = 'Pinocchio';
        $form['event[wikis][0]'] = 'en.wikipedia';
        $this->crawler = $this->client->submit($form);

        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'The_Lion_King']);
        static::assertNull($event);

        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['domain' => 'de.wikipedia']);
        static::assertNull($eventWiki);

        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Pinocchio']);
        $this->entityManager->refresh($event);
        static::assertNotNull($event);

        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['event' => $event]);
        static::assertNotNull($eventWiki);

        static::assertEquals(
            'en.wikipedia',
            $eventWiki->getDomain()
        );
    }

    /**
     * Test how child wikis are handled when a family wiki is added, and when stats are generated.
     */
    public function familyWikiSpec(): void
    {
        // First create multiple 'orphan' wikis (that don't have an associated family wiki yet).
        /** @var Event $event */
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Pinocchio']);
        new EventWiki($event, 'fr.wikipedia');
        new EventWiki($event, 'commons.wikimedia');
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['domain' => 'fr.wikipedia']);
        static::assertNotNull($eventWiki);

        $this->crawler = $this->client->request('GET', $this->getEventUrl().'/edit');

        // Make sure the three wikis are in the form.
        static::assertEquals('commons.wikimedia', $this->crawler->filter('#event_wikis_0')->attr('value'));
        static::assertEquals('en.wikipedia', $this->crawler->filter('#event_wikis_1')->attr('value'));
        static::assertEquals('fr.wikipedia', $this->crawler->filter('#event_wikis_2')->attr('value'));

        // Change en.wikipedia to all Wikipedias, and save.
        $form = $this->crawler->selectButton('Save')->form();
        $form['event[wikis][1]'] = '*.wikipedia';
        $this->crawler = $this->client->submit($form);

        // Both en.wikipedia and fr.wikipedia should have been deleted.
        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['domain' => 'en.wikipedia']);
        static::assertNull($eventWiki);
        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['domain' => 'fr.wikipedia']);
        static::assertNull($eventWiki);
        $eventWikis = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findBy(['event' => $event]);

        // *.wikipedia and commons.wikimedia should still remain.
        $domains = array_map(function (EventWiki $eventWiki) {
            return $eventWiki->getDomain();
        }, $eventWikis);
        static::assertEquals(['*.wikipedia', 'commons.wikimedia'], $domains);
    }

    /**
     * Test relevant errors are shown when updating an event.
     */
    private function validateSpec(): void
    {
        $this->crawler = $this->client->request('GET', $this->getEventUrl().'/edit');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $this->crawler->selectButton('Save')->form();
        $form['event[wikis][0]'] = 'invalid_wiki';
        $this->crawler = $this->client->submit($form);

        static::assertContains(
            '1 wiki is invalid.',
            $this->crawler->filter('.alert-danger')->text()
        );
    }

    /**
     * Show page, which lists participants and statistics.
     * This also tests that you can use the ID or title for routing.
     */
    private function showSpec(): void
    {
        $this->crawler = $this->client->request('GET', $this->getEventUrl());
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());

        static::assertContains(
            'Pinocchio',
            $this->crawler->filter('.page-header')->text()
        );

        // Should see the 'Settings' button, since we are logged in and are one of the organizers.
        static::assertContains(
            'Settings',
            $this->crawler->filter('.page-header')->text()
        );
    }

    /**
     * Adding/remove participants to the event.
     */
    private function participantsSpec(): void
    {
        $form = $this->crawler->selectButton('Save participants')->form();

        // Add four usernames: a valid user (twice, with different capitalization), a non-existent user,
        // and an invalid username. Some with leading and trailing spaces, and with different line breaks.
        $form['participantForm[new_participants]'] =
            "  MusikAnimal\nmusikAnimal \r\nUser_does_not_exist_1234\ninvalid|username";
        $this->crawler = $this->client->submit($form);

        static::assertContains(
            '2 usernames are invalid',
            $this->crawler->filter('.alert-danger')->text()
        );

        $form = $this->crawler->selectButton('Save participants')->form();
        $inputs = $this->crawler->filter('.participant-row input');

        // Confirm that only three of the four usernames were added.
        // The inputs appear with invalid ones first, and so aren't in alphabetical order.
        static::assertEquals('Invalid|username', $inputs->eq(0)->attr('value'));
        static::assertEquals('User does not exist 1234', $inputs->eq(1)->attr('value'));
        static::assertEquals('MusikAnimal', $inputs->eq(2)->attr('value'));

        // Remove invalid users and submit again. Inputs are indexed in alphabetical order.
        unset($form['participantForm[participants][0]']);
        unset($form['participantForm[participants][2]']);
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        $eventRepo = $this->entityManager->getRepository('Model:Event');
        $event = $eventRepo->findOneBy(['title' => 'Pinocchio']);

        static::assertEquals(
            [10584730], // User ID of MusikAnimal.
            $event->getParticipantIds()
        );
    }

    /**
     * Add/remove categories to the Event.
     */
    public function categoriesSpec(): void
    {
        // Browse to event page.
        $this->crawler = $this->client->request('GET', $this->getEventUrl());
        $this->response = $this->client->getResponse();

        $form = $this->crawler->selectButton('Save categories')->form();

        // Should have an empty row.
        static::assertEquals('', $form['categoryForm[categories][0][title]']->getValue());

        // Start with an invalid category title.
        $form['categoryForm[categories][0][title]'] = 'Invalid category 12345';
        $this->crawler = $this->client->submit($form);
        static::assertContains(
            '1 category is invalid',
            $this->crawler->filter('.alert-danger')->text()
        );
        static::assertContains(
            'has-error',
            $this->crawler->filter('#categoryForm_categories_0_title')->parents()->attr('class')
        );

        // Invalid wiki.
        $form['categoryForm[categories][0][title]'] = 'Parks in Brooklyn';
        $form['categoryForm[categories][0][domain]'] = 'invalid.wikipedia.org';
        $this->crawler = $this->client->submit($form);
        static::assertContains(
            '1 category is invalid',
            $this->crawler->filter('.alert-danger')->text()
        );

        // Add a valid category and wiki (category is already set from above).
        $form['categoryForm[categories][0][domain]'] = 'en.wikipedia.org';
        $this->crawler = $this->client->submit($form);
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        // Make sure it was saved to the database.
        $eventRepo = $this->entityManager->getRepository('Model:Event');
        $event = $eventRepo->findOneBy(['title' => 'Pinocchio']);
        $this->entityManager->refresh($event); // Clear cache, unclear why this is needed.
        static::assertEquals('Parks in Brooklyn', $event->getCategories()->first()->getTitle());

        // One last pass to make sure the category is listed.
        static::assertEquals(
            'Parks in Brooklyn',
            $form->get('categoryForm[categories][0][title]')->getValue()
        );
    }

    /**
     * Cloning an Event.
     */
    private function cloneSpec(): void
    {
        $this->crawler = $this->client->request('GET', $this->getEventUrl().'/copy');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $this->crawler->selectButton('Save')->form();
        $form['event[title]'] = 'Pinocchio II';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Pinocchio_II']);

        static::assertNotNull($event);
        static::assertEquals(
            new DateTime('2017-01-01 18:00'),
            $event->getStart()
        );

        $eventWiki = $this->entityManager
            ->getRepository('Model:EventWiki')
            ->findOneBy(['event' => $event]);
        static::assertNotNull($eventWiki);
        static::assertEquals(
            '*.wikipedia',
            $eventWiki->getDomain()
        );

        static::assertEquals(
            [10584730],
            $event->getParticipantIds()
        );

        static::assertEquals('Parks in Brooklyn', $event->getCategories()->first()->getTitle());
    }

    /**
     * Test event deletion.
     */
    private function deleteSpec(): void
    {
        static::assertCount(
            2, // There was a cloned event, see self::cloneSpec()
            $this->entityManager->getRepository('Model:Event')->findAll()
        );

        $this->crawler = $this->client->request('GET', $this->getEventUrl().'/delete');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        static::assertCount(
            1,
            $this->entityManager->getRepository('Model:Event')->findAll()
        );
    }

    /**
     * Generate a URL to the program, going by $this->programId.
     * @return string
     */
    private function getProgramUrl(): string
    {
        return '/programs/'.$this->programId;
    }

    /**
     * Generate a URL to the event, going by $this->programId and $this->eventId.
     * @return string
     */
    private function getEventUrl(): string
    {
        return '/programs/'.$this->programId.'/events/'.$this->eventId;
    }
}
