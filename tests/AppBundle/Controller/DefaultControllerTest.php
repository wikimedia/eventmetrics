<?php
/**
 * This file contains only the DefaultControllerTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use DMS\PHPUnitExtensions\ArraySubset\Assert;

/**
 * The DefaultControllerTest tests the home page and related actions.
 */
class DefaultControllerTest extends DatabaseAwareWebTestCase
{
    /**
     * The home page.
     */
    public function testIndex(): void
    {
        $this->crawler = $this->client->request('GET', '/');
        $this->response = $this->client->getResponse();
        static::assertEquals(200, $this->response->getStatusCode());
        static::assertStringContainsString(
            'Welcome to Event Metrics',
            $this->crawler->filter('.splash-dialog')->text()
        );
    }

    /**
     * Test browsing to index when logged on.
     */
    public function testLoggedIn(): void
    {
        $this->loginUser();

        $this->crawler = $this->client->request('GET', '/');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());
    }

    /**
     * Logging out.
     */
    public function testLogout(): void
    {
        $this->loginUser();

        $this->crawler = $this->client->request('GET', '/logout');
        $this->response = $this->client->getResponse();
        static::assertEquals(302, $this->response->getStatusCode());
        static::assertNull(
            static::$container->get('session')->get('logged_in_user')
        );
    }

    /**
     * OAuth callback action.
     */
    public function testOAuthCallback(): void
    {
        $this->client->request('GET', '/oauth_callback');

        // Callback should 404 since we didn't give it anything.
        static::assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The wikis API.
     */
    public function testWikisApi(): void
    {
        $this->client->request('GET', '/api/wikis');
        $this->response = $this->client->getResponse();

        // @see https://github.com/sebastianbergmann/phpunit/issues/3494
        Assert::assertArraySubset(
            [
                'de.wikipedia' => 'dewiki_p',
                'www.wikidata' => 'wikidatawiki_p',
                'commons.wikimedia' => 'commonswiki_p',
            ],
            json_decode($this->response->getContent(), true)
        );
    }
}
