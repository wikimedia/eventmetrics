<?php
/**
 * This file contains only the EventDataControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;

/**
 * Integration/functional tests for the EventDataController.
 */
class EventDataControllerTest extends DatabaseAwareWebTestCase
{
    /**
     * Called before each test.
     */
    public function setUp()
    {
        parent::setUp();

        $this->addFixture(new LoadFixtures('extended'));
        $this->executeFixtures();

        // Creates a session for a user, which is needed so we don't
        // get redirected back to /login during the testing suite.
        $this->loginUser('MusikAnimal');
    }

    /**
     * Revisions page.
     */
    public function testRevisions()
    {
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        $this->crawler = $this->client->request(
            'GET',
            '/programs/My_fun_program/Oliver_and_Company/revisions'
        );
        $this->response = $this->client->getResponse();

        // 'Updated' is currently null (not in extended.yml fixture),
        // so the revision browser should redirect to the event page.
        static::assertEquals(302, $this->response->getStatusCode());

        // Set updated attribute then try again.
        $event->setUpdated(new \DateTime('2014-01-24T00:00:00Z'));
        $this->entityManager->persist($event);
        $this->entityManager->flush();
        $this->crawler = $this->client->request(
            'GET',
            '/programs/My_fun_program/Oliver_and_Company/revisions'
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());

        static::assertContains(
            'MusikAnimal',
            $this->crawler->filter('.event-revisions')->text()
        );
    }

    /**
     * Generating statistics.
     */
    public function testStats()
    {
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        // Without the XMLHttpRequest header (not AJAX).
        $this->crawler = $this->client->request('GET', '/events/process/'.$event->getId());
        $this->response = $this->client->getResponse();
        static::assertEquals(403, $this->response->getStatusCode());

        // Nonexistent Event.
        $this->crawler = $this->client->request(
            'GET',
            '/events/process/12345',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(404, $this->response->getStatusCode());

        $this->crawler = $this->client->request(
            'GET',
            '/events/process/'.$event->getId(),
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());

        // Quick assertion to make sure proper JSON is returned.
        // The actual statistics are tested in the EventProcessorTest.
        $ret = json_decode($this->response->getContent(), true);
        static::assertEquals('complete', $ret['status']);
        static::assertEquals(
            ['new-editors', 'wikis', 'files-uploaded', 'file-usage', 'pages-created', 'pages-improved', 'retention'],
            array_keys($ret['data'])
        );

        // Make sure the stats were saved.
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findBy(['event' => $event]);
        static::assertEquals(6, count($eventStats));
    }
}
