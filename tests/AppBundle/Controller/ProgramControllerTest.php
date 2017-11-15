<?php
/**
 * This file contains only the ProgramControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Program;
use AppBundle\Repository\ProgramRepository;

class ProgramControllerTest extends DatabaseAwareWebTestCase
{
    public function setup()
    {
        parent::setUp();

        $this->addFixture(new LoadFixtures());
        $this->executeFixtures();

        $this->client = static::createClient();
        $this->container = $this->client->getContainer();

        // Create identity mock of MusikAnimal and put it in the session.
        $identityMock = (object) ['username' => 'MusikAnimal'];
        $this->container->get('session')->set('logged_in_user', $identityMock);
    }

    public function testIndex()
    {
        $this->crawler = $this->client->request('GET', '/programs');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    public function testNew()
    {
        $this->crawler = $this->client->request('GET', '/programs/new');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains(
            'Add a new program',
            $this->crawler->filter('.page-header')->text()
        );
        $this->assertContains(
            'MusikAnimal',
            $this->crawler->filter('#form_organizerNames_0')->attr('value')
        );
    }

    public function testCreate()
    {
        $this->crawler = $this->client->request('GET', '/programs/new');
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = 'My test program';
        $this->crawler = $this->client->submit($form);
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());

        $programs = $this->entityManager->getRepository('Model:Program')->findByTitle('My test program');

        $this->assertCount(1, $programs);

        $program = $programs[0];
        $this->assertNotNull($program);

        $programRepo = new ProgramRepository();
        $programRepo->setContainer($this->container);
        $program->setRepository($programRepo);

        $this->assertEquals(['MusikAnimal'], $program->getOrganizerNames());
    }
}
