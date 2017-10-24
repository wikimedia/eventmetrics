<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertContains('Welcome to Grant Metrics', $crawler->filter('div')->text());
    }
}
