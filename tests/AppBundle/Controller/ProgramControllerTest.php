<?php
/**
 * This file contains only the ProgramControllerTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use AppBundle\DataFixtures\ORM\LoadFixtures;

/**
 * Integration/functional tests for the ProgramController.
 */
class ProgramControllerTest extends DatabaseAwareWebTestCase
{
    /** @var int ID of the Program. */
    private $programId;

    public function setUp(): void
    {
        parent::setUp();

        // This tests runs code that throws exceptions, and we don't
        // want that in the test output.
        $this->suppressErrors();
    }

    /**
     * Workflow, including creating, updating and deleting programs.
     */
    public function testWorkflow(): void
    {
        $this->executeFixtures();

        $this->loginUser();

        $this->indexSpec();
        $this->newSpec();
        $this->createSpec();
        $this->updateSpec();

        $this->crawler = $this->client->request('GET', '/programs');
        static::assertStringContainsString(
            'The Lion King',
            $this->crawler->filter('.programs-list')->text()
        );

        $this->showSpec();

        $this->deleteSpec();
    }

    /**
     * Test while logged in as a non-organizer, ensuring edit options aren't available.
     */
    public function testNonOrganizer(): void
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
    private function indexSpec(): void
    {
        $this->crawler = $this->client->request('GET', '/programs');
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
    }

    /**
     * Form to create a new program.
     */
    private function newSpec(): void
    {
        $this->crawler = $this->client->request('GET', '/programs/new');

        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertStringContainsString(
            'Create a new program',
            $this->crawler->filter('.page-header')->text()
        );
        static::assertEquals(
            'MusikAnimal',
            $this->crawler->filter('#program_organizers_0')->attr('value')
        );
    }

    /**
     * Creating a new program.
     */
    private function createSpec(): void
    {
        $form = $this->crawler->selectButton('Save')->form();
        $form['program[title]'] = ' My test program ';
        $this->crawler = $this->client->submit($form);

        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        $program = $this->entityManager
            ->getRepository('Model:Program')
            ->findOneBy(['title' => 'My_test_program']);
        static::assertNotNull($program);
        static::assertEquals(['MusikAnimal'], $program->getOrganizerNames());

        // Used throughout the rest of the specs.
        $this->programId = $program->getId();
    }

    /**
     * Updating a program.
     */
    private function updateSpec(): void
    {
        $this->crawler = $this->client->request('GET', '/programs/'.$this->programId.'/edit');
        $form = $this->crawler->selectButton('Save')->form();
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
    private function showSpec(): void
    {
        $this->crawler = $this->client->request('GET', '/programs/'.$this->programId);
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
        static::assertStringContainsString(
            'The Lion King',
            $this->crawler->filter('.page-header')->text()
        );
        static::assertStringContainsString(
            'MusikAnimal',
            $this->crawler->filter('.programs-organizers')->text()
        );

        // Should see the 'Settings' button, since we are logged in and are one of the organizers.
        static::assertStringContainsString(
            'Settings',
            $this->crawler->filter('.page-header')->text()
        );
    }

    /**
     * Test program deletion.
     */
    private function deleteSpec(): void
    {
        static::assertCount(
            1,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );

        $this->crawler = $this->client->request('GET', '/programs/'.$this->programId.'/delete');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());

        static::assertCount(
            0,
            $this->entityManager->getRepository('Model:Program')->findAll()
        );
    }
}
