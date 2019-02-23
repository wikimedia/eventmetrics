<?php
/**
 * This file contains only the EventDataControllerTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\Job;

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

        $this->generateStats($event);

        // Revision browser should now load.
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
            '/en\.wikipedia.*www\.wikidata.*Samwilson.*MusikAnimal/s',
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

        // This method also flushes to the database, hence the above EventCategory will be saved.
        $this->generateStats($event);

        $this->crawler = $this->client->request(
            'GET',
            '/programs/My_fun_program/Oliver_and_Company/revisions'
        );
        $this->response = $this->client->getResponse();

        // Exactly 29 edits.
        static::assertEquals(29, $this->crawler->filter('.event-revision')->count());

        // 14 edits to enwiki.
        static::assertEquals(
            14,
            substr_count($this->response->getContent(), '<td class="text-nowrap">en.wikipedia</td>')
        );

        // 12 are to [[Domino Park]].
        static::assertEquals(
            12,
            substr_count($this->response->getContent(), 'https://en.wikipedia.org/wiki/Domino Park')
        );

        // 3 are files.
        static::assertEquals(
            3,
            substr_count($this->response->getContent(), '/wiki/File:')
        );
    }

    /**
     * Generating statistics.
     */
    public function testProcessEndpoint(): void
    {
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        // Nonexistent Event.
        $this->crawler = $this->client->request(
            'POST',
            '/events/process/12345',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(404, $this->response->getStatusCode());

        // Make a request to process the event.
        $this->crawler = $this->client->request(
            'POST',
            '/events/process/'.$event->getId(),
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->response = $this->client->getResponse();
        static::assertEquals(204, $this->response->getStatusCode());

        // Make sure the stats were saved.
        $eventStats = $this->entityManager
            ->getRepository('Model:EventStat')
            ->findBy(['event' => $event]);
        static::assertEquals(15, count($eventStats));
    }

    /**
     * Test the job status and delete job actions.
     */
    public function testJobApis(): void
    {
        // Simulate the different states and test that the endpoint returns the right value.

        /** @var string[] $states Keys are the constants, values are what the API should return. */
        $states = [
            'QUEUED' => 'queued',
            'STARTED' => 'started',
            'FAILED_TIMEOUT' => 'failed-timeout',
            'FAILED_UNKNOWN' => 'failed-unknown',
        ];

        foreach ($states as $constant => $value) {
            // No idea why we have to fetch the Event on every iteration; something clashing with PHPUnit and Doctrine.
            $event = $this->entityManager
                ->getRepository('Model:Event')
                ->findOneBy(['title' => 'Oliver_and_Company']);
            $event->clearJobs();
            $this->entityManager->persist($event);
            $this->entityManager->flush();

            $job = new Job($event);
            $job->setStatus(constant('AppBundle\Model\Job::STATUS_'.$constant));
            $this->entityManager->persist($job);
            $this->entityManager->flush();

            $this->client->request('GET', '/events/job-status/'.$event->getId());
            $this->response = $this->client->getResponse();
            static::assertEquals($value, json_decode($this->response->getContent(), true)['status']);
        }

        // Job gets removed when completed.
        $this->client->request('DELETE', '/events/delete-job/'.$event->getId());
        static::assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->request('GET', '/events/job-status/'.$event->getId());
        $this->response = $this->client->getResponse();
        static::assertEquals(
            'complete',
            json_decode($this->response->getContent(), true)['status']
        );

        // Asking for a nonexistent Event.
        $this->client->request('GET', '/events/job-status/9999');
        static::assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Update the stats, creating a new Job for the Event and flushing to the database.
     * @param Event $event
     */
    private function generateStats(Event $event): void
    {
        // Update the stats, creating a new Job for the Event and flushing to the database.
        $job = new Job($event);
        $this->entityManager->persist($job);
        $this->entityManager->flush();
        $jobHandler = self::$kernel->getContainer()->get('AppBundle\Service\JobHandler');
        $jobHandler->spawn($job);
    }

    /**
     * Event Summary report.
     */
    public function testEventSummary(): void
    {
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        // Make a request to process the event.
        $this->crawler = $this->client->request(
            'POST',
            '/events/process/'.$event->getId(),
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );

        $this->client->request(
            'GET',
            "/programs/{$event->getProgram()->getId()}/events/{$event->getId()}/summary?format=wikitext"
        );
        $this->response = $this->client->getResponse();

        // Basic assertion to ensure data is being outputed.
        static::assertContains("Pages created\n| 3", $this->response->getContent());
    }

    /**
     * Pages Created report.
     */
    public function testPagesCreated(): void
    {
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        // Make a request to process the event.
        $this->crawler = $this->client->request(
            'POST',
            '/events/process/'.$event->getId(),
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );

        $this->client->request(
            'GET',
            "/programs/{$event->getProgram()->getId()}/events/{$event->getId()}/pages-created?format=wikitext"
        );
        $this->response = $this->client->getResponse();

        $snippet = <<<EOD
| [https://en.wikipedia.org/wiki/Domino_Park Domino Park]
| [https://en.wikipedia.org/wiki/User:MusikAnimal MusikAnimal]
| en.wikipedia
| {{FORMATNUM:12}}
| +{{FORMATNUM:4641}}
EOD;
        static::assertcontains($snippet, $this->response->getContent());
    }
}
