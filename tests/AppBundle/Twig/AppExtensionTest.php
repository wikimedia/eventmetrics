<?php
/**
 * This file contains only the AppExtensionTest class.
 */

namespace AppBundle\Twig;

use Krinkle\Intuition\Intuition;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the AppExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class AppExtensionTest extends GrantMetricsTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var \AppBundle\Twig\AppExtension Instance of class */
    protected $appExtension;

    /**
     * Set class instance.
     */
    public function setUp()
    {
        parent::setUp();

        $client = static::createClient();
        $this->container = $client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $intuition = new Intuition();
        $this->appExtension = new AppExtension($this->container, $stack, $session, $intuition);
    }

    /**
     * Intution methods.
     */
    public function testIntution()
    {
        static::assertEquals('en', $this->appExtension->getLang());
        static::assertEquals('English', $this->appExtension->getLangName());

        $allLangs = $this->appExtension->getAllLangs();

        // There should be a bunch.
        static::assertGreaterThan(20, count($allLangs));

        // Keys should be the language codes, with name as the values.
        static::assertArraySubset(['en' => 'English'], $allLangs);
        static::assertArraySubset(['de' => 'Deutsch'], $allLangs);
        static::assertArraySubset(['es' => 'Español'], $allLangs);

        // Testing if the language is RTL.
        static::assertFalse($this->appExtension->isRTLLang('en'));
        static::assertTrue($this->appExtension->isRTLLang('ar'));
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods()
    {
        static::assertEquals(7, strlen($this->appExtension->gitShortHash()));
        static::assertEquals(40, strlen($this->appExtension->gitHash()));
    }
}
