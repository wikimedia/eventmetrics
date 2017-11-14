<?php
/**
 * This file contains only the DefaultControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends DatabaseAwareWebTestCase
{

    public function setup()
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
    }

    public function testIndex()
    {
        $this->crawler = $this->client->request('GET', '/');
        $this->response = $this->client->getResponse();
        $this->assertEquals(200, $this->response->getStatusCode());
        $this->assertContains('Welcome to Grant Metrics', $this->crawler->filter('.splash-dialog')->text());
    }

    public function testLogout()
    {
        // Create identity mock of MusikAnimal and put it in the session.
        $identityMock = (object) ['username' => 'MusikAnimal'];
        $this->container->get('session')->set('logged_in_user', $identityMock);

        $this->crawler = $this->client->request('GET', '/logout');
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());
        $this->assertNull(
            $this->container->get('session')->get('logged_in_user')
        );
    }
}
