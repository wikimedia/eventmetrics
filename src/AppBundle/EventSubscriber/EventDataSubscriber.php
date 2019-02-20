<?php
declare(strict_types=1);

namespace AppBundle\EventSubscriber;

use AppBundle\Controller\EventDataController;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An EventDataSubscriber provides before-filtering to the EventDataController.
 */
class EventDataSubscriber implements EventSubscriberInterface
{
    /** @var Router The Symfony Router, used to redirect to other controllers/actions. */
    private $router;

    /**
     * EventDataSubscriber constructor.
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Capture controller instantiation. We need to check if it's for EventDataController and the desired actions.
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event): void
    {
        /**
         * Skip if a Closure (may happen in Symfony).
         * @codeCoverageIgnoreStart
         */
        if (!is_array($event->getController())) {
            return;
        }
        // @codeCoverageIgnoreEnd

        /** @var EventDataController $controller */
        $controller = $event->getController()[0];

        /** @var string $action */
        $action = $event->getController()[1];

        // For the time being, we are only filtering on report actions in the EventDataController.
        if (!$controller instanceof EventDataController || false === strpos($action, 'ReportAction')) {
            return;
        }

        // Redirect to event page if statistics have not yet been generated.
        if (null === $controller->getEvent()->getUpdated()) {
            $redirectUrl = $this->router->generate('Event', [
                'programId' => $controller->getProgram()->getId(),
                'eventId' => $controller->getEvent()->getId(),
            ]);

            $event->setController(function () use ($redirectUrl) {
                return new RedirectResponse($redirectUrl);
            });
        }
    }

    /**
     * Specify the desired events to subscribe to.
     * @return string[]
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
