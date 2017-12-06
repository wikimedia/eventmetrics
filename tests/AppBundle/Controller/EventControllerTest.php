<?php
/**
 * This file contains only the EventControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use DateTime;

/**
 * Integration/functional tests for the ProgramController.
 */
class EventControllerTest extends DatabaseAwareWebTestCase
{
    public function setup()
    {
        parent::setUp();

        $this->addFixture(new LoadFixtures());
        $this->executeFixtures();

        // Create identity mock of MusikAnimal and put it in the session.
        $identityMock = (object)['username' => 'MusikAnimal'];
        $this->container->get('session')->set('logged_in_user', $identityMock);
    }

    /**
     * No index of events without program in URL, just redirects to programs page.
     */
    public function testIndex()
    {
        $this->crawler = $this->client->request('GET', '/events');
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());
    }

    public function testWorkflow()
    {
        $this->indexSpec();
        $this->newSpec();
        $this->createSpec();
    }

    /**
     * Index of events for the program.
     */
    private function indexSpec()
    {
        // 'My_fun_program' was already created via fixtures.
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Form to create a new event.
     */
    private function newSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/My_fun_program/new');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains(
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
        $form['form[wikis][0]'] = 'enwiki';
        $form['form[enableTime]']->tick();
        $form['form[start][date]'] = '2017-01-01 18:00';
        $form['form[start][time]'] = '18:00';
        $form['form[end][date]'] = '2017-02-01';
        $form['form[end][time]'] = '21:00';
        $form['form[timezone]'] = 'America/New_York';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());

        $events = $this->entityManager->getRepository('Model:Event')->findByTitle('The_Lion_King');
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertNotNull($event);

        $this->assertEquals(
            'My_fun_program',
            $event->getProgram()->getTitle()
        );
        $this->assertEquals(
            new DateTime('2017-01-01 18:00'),
            $event->getStart()
        );

        $eventWikis = $this->entityManager->getRepository('Model:EventWiki')->findBy([
            'event' => $event
        ]);
        $this->assertCount(1, $eventWikis);
        $eventWiki = $eventWikis[0];
        $this->assertNotNull($eventWiki);
        $this->assertEquals(
            'enwiki',
            $eventWiki->getDbName()
        );
    }
}
