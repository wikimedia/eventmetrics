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
    public function setup()
    {
        parent::setUp();

        $this->addFixture(new LoadFixtures('extended'));
        $this->executeFixtures();
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
        $this->assertEquals(200, $this->response->getStatusCode());

        $this->assertContains(
            'Samwilson',
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
        $this->assertEquals(403, $this->response->getStatusCode());

        // Nonexistent Event.
        $this->crawler = $this->client->request(
            'GET',
            '/events/process/12345',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->response = $this->client->getResponse();
        $this->assertEquals(404, $this->response->getStatusCode());

        $this->crawler = $this->client->request(
            'GET',
            '/events/process/'.$event->getId(),
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->response = $this->client->getResponse();
        $this->assertEquals(200, $this->response->getStatusCode());

        // Quick assertion to make sure proper JSON is returned.
        // The actual statistics are tested in the EventProcessorTest.
        $ret = json_decode($this->response->getContent(), true);
        $this->assertEquals('complete', $ret['status']);
        $this->assertEquals(
            ['new-editors', 'wikis', 'pages-created', 'pages-improved', 'retention'],
            array_keys($ret['data'])
        );

        // Make sure the stats were saved.
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findBy(['event' => $event]);
        $this->assertEquals(4, count($eventStats));
    }
}
