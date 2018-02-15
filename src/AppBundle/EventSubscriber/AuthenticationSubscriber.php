<?php
/**
 * This file contains only the AuthenticationSubscriber.
 */

namespace AppBundle\EventSubscriber;

use AppBundle\Controller\EntityController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The AuthenticationSubscriber serves as a before-filter called before
 * every Controller action. It performs the authentication checks
 * and will redirect or throw errors accordingly.
 */
class AuthenticationSubscriber implements EventSubscriberInterface
{
    /** @var SessionInterface Symfony's session interface. */
    protected $session;

    /**
     * Constructor for the AuthenticationSubscriber.
     * @param SessionInterface $session Provided by Symfony dependency injection.
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Called before controller actions are executed.
     * @param  FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        /**
         * $controller passed can be either a class or a Closure.
         * This is not usual in Symfony but it may happen.
         * If it is a class, it comes in array format.
         * Also return if we're not dealing with an EntityController
         * which is the only place we do authentication stuff.
         */
        $entityContClass = 'AppBundle\Controller\EntityController';
        if (!is_array($controller) || !is_subclass_of($controller[0], $entityContClass)) {
            return;
        }

        // Redirect to /login if they aren't logged in at all.
        if (!$this->session->get('logged_in_user')) {
            $route = '/login?redirect='.$event->getRequest()->getPathInfo();
            $event->setController(function () use ($route) {
                return new RedirectResponse($route);
            });
        }
    }

    /**
     * Implements abstract method, specifying life cycle event.
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
