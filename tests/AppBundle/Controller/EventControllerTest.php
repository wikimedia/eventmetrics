<?php
/**
 * This file contains only the EventControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use DateTime;

/**
 * Integration/functional tests for the EventController.
 */
class EventControllerTest extends DatabaseAwareWebTestCase
{
    /**
     * Called before each test.
     */
    public function setUp()
    {
        parent::setUp();

        // This tests runs code that throws exceptions, and we don't
        // want that in the test output.
        $this->suppressErrors();
    }

    /**
     * No index of events without program in URL, just redirects to programs page.
     */
    public function testIndex()
    {
        $this->loginUser('Test user');
        $this->crawler = $this->client->request('GET', '/events');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());
    }

    /**
     * Workflow, including creating, updating and deleting events.
     */
    public function testWorkflow()
    {
        // Load basic fixtures.
        $this->addFixture(new LoadFixtures());
        $this->executeFixtures();

        $this->loginUser();

        $this->indexSpec();
        $this->newSpec();
        $this->createSpec();
        $this->validateSpec();
        $this->updateSpec();
        $this->familyWikiSpec();
        $this->showSpec();
        $this->participantsSpec();
        $this->cloneSpec();
        $this->deleteSpec();
    }

    /**
     * Attempting to browse to /programs when not logged in at all.
     */
    public function testLoggedOut()
    {
        // 'My_fun_program' was already created via fixtures.
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program');
        $this->response = $this->client->getResponse();
        static::assertEquals(307, $this->response->getStatusCode());
    }

    /**
     * Test while logged in as a non-organizer, ensuring edit options aren't available.
     */
    public function testNonOrganizer()
    {
        // Load more test events.
        $this->addFixture(new LoadFixtures('extended'));
        $this->executeFixtures();

        $this->loginUser('Not an organizer');

        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/Oliver_and_Company');
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
        // $this->crawler = $this->client->request('GET', '/programs/My_fun_program/edit/Oliver_and_Company');
        // static::assertEquals(403, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Index of events for the program.
     */
    private function indexSpec()
    {
        // 'My_fun_program' was already created via fixtures.
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program');
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
    }

    /**
     * Form to create a new event.
     */
    private function newSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/new');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertContains(
            'Create a new event',
            $this->crawler->filter('.page-header')->text()
        );
    }

    /**
     * Creating a new event.
     */
    private function createSpec()
    {
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = ' The Lion King ';
        $form['form[wikis][0]'] = 'dewiki';
        $form['form[start]'] = '2017-01-01 18:00';
        $form['form[end]'] = '2017-02-01 21:00';
        $form['form[timezone]'] = 'America/New_York';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'The_Lion_King']);

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
    private function updateSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/edit/The_Lion_King');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $this->crawler->selectButton('Submit')->form();

        $form['form[title]'] = 'Pinocchio';
        $form['form[wikis][0]'] = 'en.wikipedia';
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
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function familyWikiSpec()
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

        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/edit/Pinocchio');

        // Make sure the three wikis are in the form.
        static::assertEquals('commons.wikimedia', $this->crawler->filter('#form_wikis_0')->attr('value'));
        static::assertEquals('en.wikipedia', $this->crawler->filter('#form_wikis_1')->attr('value'));
        static::assertEquals('fr.wikipedia', $this->crawler->filter('#form_wikis_2')->attr('value'));

        // Change en.wikipedia to all Wikipedias, and save.
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[wikis][1]'] = '*.wikipedia';
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
    private function validateSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/edit/The_Lion_King');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[wikis][0]'] = 'invalid_wiki';
        $this->crawler = $this->client->submit($form);

        static::assertContains(
            '1 wiki is invalid.',
            $this->crawler->filter('.alert-danger')->text()
        );
    }

    /**
     * Show page, which lists participants and statistics.
     */
    private function showSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/Pinocchio');
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());

        // Should see the 'edit event', since we are logged in and are one of the organizers.
        static::assertContains(
            'edit event',
            $this->crawler->filter('.page-header')->text()
        );
    }

    /**
     * Adding/remove participants to the event.
     */
    private function participantsSpec()
    {
        $form = $this->crawler->selectButton('Save participants')->form();

        $form['form[new_participants]'] = "  MusikAnimal  \r\nUser_does_not_exist_1234";
        $this->crawler = $this->client->submit($form);

        static::assertContains(
            '1 username is invalid',
            $this->crawler->filter('.alert-danger')->text()
        );

        $form = $this->crawler->selectButton('Save participants')->form();
        $inputs = $this->crawler->filter('.participant-row input');

        static::assertEquals('User does not exist 1234', $inputs->first()->attr('value'));
        static::assertEquals('MusikAnimal', $inputs->last()->attr('value'));

        // Remove invalid user and submit again.
        unset($form['form[participants][1]']);
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
     * Cloning an Event.
     */
    private function cloneSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/copy/Pinocchio');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = 'Pinocchio II';
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
    }

    /**
     * Test event deletion.
     */
    private function deleteSpec()
    {
        static::assertCount(
            2, // There was a cloned event, see self::cloneSpec()
            $this->entityManager->getRepository('Model:Event')->findAll()
        );

        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/delete/Pinocchio');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        static::assertCount(
            1,
            $this->entityManager->getRepository('Model:Event')->findAll()
        );
    }
}
