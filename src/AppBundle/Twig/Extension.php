<?php
/**
 * This file contains only the Extension class.
 */

declare(strict_types=1);

namespace AppBundle\Twig;

use Krinkle\Intuition\Intuition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig_Extension;

/**
 * The parent class for all of the Twig extensions, in order to centralize the i18n set-up.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
abstract class Extension extends Twig_Extension
{
    /** @var ContainerInterface The DI container. */
    protected $container;

    /** @var RequestStack The request stack. */
    protected $requestStack;

    /** @var SessionInterface User's current session. */
    protected $session;

    /** @var Intuition The i18n object. */
    protected $intuition;

    /**
     * Extension constructor.
     * @param ContainerInterface $container The DI container.
     * @param RequestStack $requestStack The request stack.
     * @param SessionInterface $session
     * @param Intuition $intuition
     */
    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack,
        SessionInterface $session,
        Intuition $intuition
    ) {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->intuition = $intuition;
    }

    /**
     * Shorthand to get the current request from the request stack.
     * @return Request
     * There is no request stack in the tests.
     * @codeCoverageIgnore
     */
    protected function getCurrentRequest(): Request
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }
}
