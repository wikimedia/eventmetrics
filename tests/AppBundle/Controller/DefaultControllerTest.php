<?php
/**
 * This file contains only the DefaultControllerTest class.
 */

namespace Tests\AppBundle\Controller;

/**
 * The DefaultControllerTest tests the home page and related actions.
 */
class DefaultControllerTest extends DatabaseAwareWebTestCase
{
    /**
     * The home page.
     */
    public function testIndex()
    {
        $this->crawler = $this->client->request('GET', '/');
        $this->response = $this->client->getResponse();
        $this->assertEquals(200, $this->response->getStatusCode());
        $this->assertContains('Welcome to Grant Metrics', $this->crawler->filter('.splash-dialog')->text());
    }

    /**
     * Test browsing to index when logged on.
     */
    public function testLoggedIn()
    {
        $this->loginUser();

        $this->crawler = $this->client->request('GET', '/');
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());
    }

    /**
     * Logging out.
     */
    public function testLogout()
    {
        $this->loginUser();

        $this->crawler = $this->client->request('GET', '/logout');
        $this->response = $this->client->getResponse();
        $this->assertEquals(302, $this->response->getStatusCode());
        $this->assertNull(
            $this->container->get('session')->get('logged_in_user')
        );
    }

    /**
     * OAuth callback action.
     */
    public function testOAuthCallback()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/oauth_callback');

        // Callback should 404 since we didn't give it anything.
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }
}
