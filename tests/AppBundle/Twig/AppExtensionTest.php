<?php
/**
 * This file contains only the AppExtensionTest class.
 */

namespace AppBundle\Twig;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use AppBundle\Twig\AppExtension;
use AppBundle\Twig\Extension;

/**
 * Tests for the AppExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class AppExtensionTest extends WebTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var AppBundle\Twig\AppExtension Instance of class */
    protected $appExtension;

    /**
     * Set class instance.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $this->appExtension = new AppExtension($this->container, $stack, $session);
    }

    /**
     * Intution methods.
     */
    public function testIntution()
    {
        $this->assertEquals('en', $this->appExtension->getLang());
        $this->assertEquals('English', $this->appExtension->getLangName());

        $allLangs = $this->appExtension->getAllLangs();

        // There should be a bunch.
        $this->assertGreaterThan(20, count($allLangs));

        // Keys should be the language codes, with name as the values.
        $this->assertArraySubset(['en' => 'English'], $allLangs);
        $this->assertArraySubset(['de' => 'Deutsch'], $allLangs);
        $this->assertArraySubset(['es' => 'Español'], $allLangs);

        // Testing if the language is RTL.
        $this->assertFalse($this->appExtension->intuitionIsRTLLang('en'));
        $this->assertTrue($this->appExtension->intuitionIsRTLLang('ar'));
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods()
    {
        $this->assertEquals(7, strlen($this->appExtension->gitShortHash()));
        $this->assertEquals(40, strlen($this->appExtension->gitHash()));
    }
}
