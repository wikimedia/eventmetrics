<?php
/**
 * This file contains only the Extension class.
 */

namespace AppBundle\Twig;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Intuition;
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
    private $intuition;

    /**
     * Extension constructor.
     * @param ContainerInterface $container The DI container.
     * @param RequestStack $requestStack The request stack.
     * @param SessionInterface $session
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, SessionInterface $session)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->session = $session;
    }

    /**
     * Get an Intuition object, set to the current language based on the query string or session
     * of the current request.
     * @return Intuition
     * @throws \Exception If the 'i18n/en.json' file doesn't exist (as it's the default).
     */
    protected function getIntuition()
    {
        // Don't recreate the object.
        if ($this->intuition instanceof Intuition) {
            return $this->intuition;
        }

        $useLang = 'en';

        // Current request doesn't exist in unit tests, in which case we'll fall back to English.
        if ($this->requestStack->getCurrentRequest() !== null) {
            $useLang = $this->getIntuitionLang();

            // Save the language to the session.
            if ($this->session->get('lang') !== $useLang) {
                $this->session->set('lang', $useLang);
            }
        }

        // Set up Intuition, using the selected language.
        $intuition = new Intuition('grantmetrics');
        $path = $this->container->getParameter('kernel.root_dir').'/../i18n';
        $intuition->registerDomain('grantmetrics', $path);
        $intuition->setLang(strtolower($useLang));

        $this->intuition = $intuition;
        return $intuition;
    }

    /**
     * Shorthand to get the current request from the request stack.
     * @return Request
     * There is no request stack in the tests.
     * @codeCoverageIgnore
     */
    protected function getCurrentRequest()
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }

    /**
     * Determine the interface language, either from the current request or session.
     * @return string
     */
    private function getIntuitionLang()
    {
        $queryLang = $this->requestStack->getCurrentRequest()->query->get('uselang');
        $sessionLang = $this->session->get('lang');

        if (!empty($queryLang)) {
            return $queryLang;
        } elseif (!empty($sessionLang)) {
            return $sessionLang;
        }

        // English as default.
        return 'en';
    }

    /**
     * Get an i18n message.
     * @param string|array $message
     * @param array $vars
     * @return mixed|null|string
     */
    public function intuitionMessage($message = '', $vars = [])
    {
        if (is_array($message)) {
            $vars = $message;
            $message = $message[0];
            $vars = array_slice($vars, 1);
        }
        return $this->getIntuition()->msg($message, [
            'domain' => 'grantmetrics',
            'variables' => $vars
        ]);
    }
}
