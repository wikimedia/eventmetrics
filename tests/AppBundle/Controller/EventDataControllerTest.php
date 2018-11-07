<?php
/**
 * This file contains only the EventDataControllerTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\EventCategory;

/**
 * Integration/functional tests for the EventDataController.
 */
class EventDataControllerTest extends DatabaseAwareWebTestCase
{
    /**
     * Called before each test.
     */
    public function setUp(): void
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
    public function testRevisions(): void
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
        $event->setUpdated(new \DateTime('2018-09-24T00:00:00Z'));
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

        $this->wikitextSpec();
        $this->csvSpec();
    }

    /**
     * Test wikitext export.
     */
    private function wikitextSpec(): void
    {
        $this->crawler = $this->client->request(
            'GET',
            '/programs/My_fun_program/Oliver_and_Company/revisions?format=wikitext'
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
        static::assertContains('text/plain', $this->response->headers->get('content-type'));
        static::assertRegExp(
            '/en\.wikipedia, www\.wikidata.*Samwilson.*MusikAnimal/s',
            $this->response->getContent()
        );
    }

    /**
     * Test CSV export.
     */
    private function csvSpec(): void
    {
        $this->crawler = $this->client->request(
            'GET',
            '/programs/My_fun_program/Oliver_and_Company/revisions?format=csv'
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
        static::assertContains('text/csv', $this->response->headers->get('content-type'));
        static::assertRegExp(
            '/en\.wikipedia.*MusikAnimal.*wikidata\.org.*Samwilson/s',
            $this->response->getContent()
        );
    }

    /**
     * Introduce a category then test the revision output is filtered accordingly.
     */
    public function testCategory(): void
    {
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);
        new EventCategory($event, 'Parks in Brooklyn', 'en.wikipedia');

        // Also set updated attribute, otherwise revision browser won't show.
        $event->setUpdated(new \DateTime('2018-09-24T00:00:00Z'));

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->crawler = $this->client->request(
            'GET',
            '/programs/My_fun_program/Oliver_and_Company/revisions'
        );
        $this->response = $this->client->getResponse();

        // Exactly 31 edits.
        static::assertEquals(31, $this->crawler->filter('.event-revision')->count());

        // 12 edits to enwiki.
        static::assertEquals(
            12,
            substr_count($this->response->getContent(), '<td class="text-nowrap">en.wikipedia</td>')
        );

        // All are to [[Domino Park]].
        static::assertEquals(
            12,
            substr_count($this->response->getContent(), 'https://en.wikipedia.org/wiki/Domino Park')
        );
    }

    /**
     * Generating statistics.
     */
    public function testStats(): void
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
            [
                'new-editors', 'wikis', 'files-uploaded', 'file-usage', 'items-created', 'items-improved',
                'edits', 'pages-created', 'pages-improved', 'retention',
            ],
            array_keys($ret['data'])
        );

        // Make sure the stats were saved.
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findBy(['event' => $event]);
        static::assertEquals(9, count($eventStats));
    }
}
