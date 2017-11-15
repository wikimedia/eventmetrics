<?php
/**
 * This file contains only the ControllerActionExtension class.
 */

namespace AppBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig_Extension;

/**
 * Twig extension that provides convient methods to get the names
 * of the current controller and action.
 */
class ControllerActionExtension extends Twig_Extension
{
    /** @var RequestStack The request stack. */
    protected $requestStack;

    /**
     * Extension constructor.
     * @param RequestStack $requestStack The request stack.
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Get the name of this extension.
     * @return string
     */
    public function getName()
    {
        return 'controller_action_twig_extension';
    }

    /**
     * Get all functions that this class provides.
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('getControllerName', [$this, 'getControllerName']),
            new \Twig_SimpleFunction('getActionName', [$this, 'getActionName'])
        ];
    }

    /**
     * Get current controller name
     *
     * @return string
     * There is no request stack in unit tests.
     * @codeCoverageIgnore
    */
    public function getControllerName()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request) {
            $pattern = "#Controller\\\([a-zA-Z]*)Controller#";
            $matches = [];
            preg_match($pattern, $request->get('_controller'), $matches);

            return strtolower($matches[1]);
        }
    }

    /**
     * Get current action name
     *
     * @return string
     * There is no request stack in unit tests.
     * @codeCoverageIgnore
    */
    public function getActionName()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request) {
            $pattern = "#::([a-zA-Z]*)Action#";
            $matches = [];
            preg_match($pattern, $request->get('_controller'), $matches);

            return $matches[1];
        }
    }
}
