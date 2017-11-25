<?php
/**
 * This file contains only the ProgramControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Program;
use AppBundle\Repository\ProgramRepository;

/**
 * Integration/functional tests for the ProgramController.
 */
class ProgramControllerTest extends DatabaseAwareWebTestCase
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
     * Index page, listing all the viewing organizer's programs.
     */
    public function testIndex()
    {
        $this->crawler = $this->client->request('GET', '/programs');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->createProgram('My test program');

        // Test again, making sure the new program is listed on the page.
        $this->crawler = $this->client->request('GET', '/programs');
        $this->assertContains(
            'My test program',
            $this->crawler->filter('.programs-list')->text()
        );
    }

    /**
     * Form to create a new program.
     */
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

    /**
     * Creating a new program.
     */
    public function testCreate()
    {
        $this->createProgram('My test program');

        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());

        $programs = $this->entityManager->getRepository('Model:Program')->findByTitle('My_test_program');
        $this->assertCount(1, $programs);
        $program = $programs[0];
        $this->assertNotNull($program);

        $this->assertEquals(['MusikAnimal'], $program->getOrganizerNames());
    }

    /**
     * Updating a program.
     */
    public function testUpdate()
    {
        $this->createProgram('My test program');
        $this->crawler = $this->client->request('GET', '/programs/edit/My_test_program');
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = 'The Lion King';
        $this->crawler = $this->client->submit($form);

        $programs = $this->entityManager->getRepository('Model:Program')->findByTitle('The_Lion_King');
        $this->assertCount(1, $programs);
        $program = $programs[0];
        $this->assertNotNull($program);
    }

    /**
     * Test program deletion.
     */
    public function testDelete()
    {
        $this->createProgram('My test program');
        $this->assertCount(
            1,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );

        $this->crawler = $this->client->request('GET', '/programs/delete/My_test_program');
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());

        $this->assertCount(
            0,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );
    }

    /**
     * Creates a test program.
     */
    private function createProgram($title)
    {
        $this->crawler = $this->client->request('GET', '/programs/new');
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = $title;
        $this->crawler = $this->client->submit($form);
    }
}
