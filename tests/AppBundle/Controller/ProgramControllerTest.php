<?php
/**
 * This file contains only the ProgramControllerTest class.
 */

namespace Tests\AppBundle\Controller;

/**
 * Integration/functional tests for the ProgramController.
 */
class ProgramControllerTest extends DatabaseAwareWebTestCase
{
    public function setup()
    {
        parent::setUp();

        $this->executeFixtures();

        // Create identity mock of MusikAnimal and put it in the session.
        $identityMock = (object)['username' => 'MusikAnimal'];
        $this->container->get('session')->set('logged_in_user', $identityMock);
    }

    public function testWorkflow()
    {
        $this->indexSpec();
        $this->newSpec();
        $this->createSpec();
        $this->updateSpec();

        $this->crawler = $this->client->request('GET', '/programs');
        $this->assertContains(
            'The Lion King',
            $this->crawler->filter('.programs-list')->text()
        );

        $this->showSpec();

        $this->deleteSpec();
    }

    /**
     * Index page, listing all the viewing organizer's programs.
     */
    private function indexSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Form to create a new program.
     */
    private function newSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/new');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains(
            'Create a new program',
            $this->crawler->filter('.page-header')->text()
        );
        $this->assertContains(
            'MusikAnimal',
            $this->crawler->filter('#form_organizers_0')->attr('value')
        );
    }

    /**
     * Creating a new program.
     */
    private function createSpec()
    {
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = ' My test program ';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());

        $program = $this->entityManager
            ->getRepository('Model:Program')
            ->findOneBy(['title' => 'My_test_program']);
        $this->assertNotNull($program);
        $this->assertEquals(['MusikAnimal'], $program->getOrganizerNames());
    }

    /**
     * Updating a program.
     */
    private function updateSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/edit/My_test_program');
        $form = $this->crawler->selectButton('Submit')->form();
        $form['form[title]'] = 'The Lion King';
        $this->crawler = $this->client->submit($form);

        $program = $this->entityManager
            ->getRepository('Model:Program')
            ->findOneBy(['title' => 'The_Lion_King']);
        $this->assertNotNull($program);
    }

    /**
     * Showing a program.
     */
    private function showSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/The_Lion_King');
        $this->response = $this->client->getResponse();
        $this->assertEquals(200, $this->response->getStatusCode());
        $this->assertContains(
            'The Lion King',
            $this->crawler->filter('.page-header')->text()
        );
        $this->assertContains(
            'MusikAnimal',
            $this->crawler->filter('.programs-organizers')->text()
        );
    }

    /**
     * Test program deletion.
     */
    private function deleteSpec()
    {
        $this->assertCount(
            1,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );

        $this->crawler = $this->client->request('GET', '/programs/delete/The_Lion_King');
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());

        $this->assertCount(
            0,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );
    }
}
