<?php
/**
 * This file contains only the ProgramControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;

/**
 * Integration/functional tests for the ProgramController.
 */
class ProgramControllerTest extends DatabaseAwareWebTestCase
{
    public function setUp()
    {
        parent::setUp();

        // This tests runs code that throws exceptions, and we don't
        // want that in the test output.
        $this->suppressErrors();
    }

    /**
     * Workflow, including creating, updating and deleting programs.
     */
    public function testWorkflow()
    {
        $this->executeFixtures();

        $this->loginUser();

        $this->indexSpec();
        $this->newSpec();
        $this->createSpec();
        $this->updateSpec();

        $this->crawler = $this->client->request('GET', '/programs');
        static::assertContains(
            'The Lion King',
            $this->crawler->filter('.programs-list')->text()
        );

        $this->showSpec();

        $this->deleteSpec();
    }

    /**
     * Test while logged in as a non-organizer, ensuring edit options aren't available.
     */
    public function testNonOrganizer()
    {
        // Load basic fixtures, including a test program.
        $this->addFixture(new LoadFixtures());
        $this->executeFixtures();

        $this->loginUser('Not an organizer');

        $this->crawler = $this->client->request('GET', '/programs/My_fun_program');
        $this->response = $this->client->getResponse();
        static::assertEquals(403, $this->response->getStatusCode());

        /**
         * For now, you must be an organizer of a program in order to view it.
         */

        // // Should not see the 'edit program', since we are logged in and are one of the organizers.
        // static::assertNotContains(
        //     'edit program',
        //     $this->crawler->filter('.page-header')->text()
        // );
    }

    /**
     * Index page, listing all the viewing organizer's programs.
     */
    private function indexSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs');
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
    }

    /**
     * Form to create a new program.
     */
    private function newSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/new');

        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertContains(
            'Create a new program',
            $this->crawler->filter('.page-header')->text()
        );
        static::assertContains(
            'MusikAnimal',
            $this->crawler->filter('#program_organizers_0')->attr('value')
        );
    }

    /**
     * Creating a new program.
     */
    private function createSpec()
    {
        $form = $this->crawler->selectButton('Submit')->form();
        $form['program[title]'] = ' My test program ';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        $program = $this->entityManager
            ->getRepository('Model:Program')
            ->findOneBy(['title' => 'My_test_program']);
        static::assertNotNull($program);
        static::assertEquals(['MusikAnimal'], $program->getOrganizerNames());
    }

    /**
     * Updating a program.
     */
    private function updateSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/edit/My_test_program');
        $form = $this->crawler->selectButton('Submit')->form();
        $form['program[title]'] = 'The Lion King';
        $this->crawler = $this->client->submit($form);

        $program = $this->entityManager
            ->getRepository('Model:Program')
            ->findOneBy(['title' => 'The_Lion_King']);
        static::assertNotNull($program);
    }

    /**
     * Showing a program.
     */
    private function showSpec()
    {
        $this->crawler = $this->client->request('GET', '/programs/The_Lion_King');
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
        static::assertContains(
            'The Lion King',
            $this->crawler->filter('.page-header')->text()
        );
        static::assertContains(
            'MusikAnimal',
            $this->crawler->filter('.programs-organizers')->text()
        );

        // Should see the 'edit program', since we are logged in and are one of the organizers.
        static::assertContains(
            'edit program',
            $this->crawler->filter('.page-header')->text()
        );
    }

    /**
     * Test program deletion.
     */
    private function deleteSpec()
    {
        static::assertCount(
            1,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );

        $this->crawler = $this->client->request('GET', '/programs/delete/The_Lion_King');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        static::assertCount(
            0,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );
    }
}
