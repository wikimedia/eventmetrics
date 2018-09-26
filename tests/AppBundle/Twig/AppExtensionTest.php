<?php
/**
 * This file contains only the AppExtensionTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Twig;

use AppBundle\Twig\AppExtension;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the AppExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class AppExtensionTest extends GrantMetricsTestCase
{

    /** @var \AppBundle\Twig\AppExtension Instance of class */
    protected $appExtension;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $stack = new RequestStack();
        $session = new Session();
        $intuition = new Intuition();
        $this->appExtension = new AppExtension(static::$container, $stack, $session, $intuition);
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods(): void
    {
        static::assertEquals(7, strlen($this->appExtension->gitShortHash()));
        static::assertEquals(40, strlen($this->appExtension->gitHash()));
    }

    /**
     * Test bdi function
     */
    public function testBDI()
    {
        static::assertEquals('<bdi>Foo</bdi>', $this->appExtension->bdi('Foo'));
    }
}
