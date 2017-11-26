<?php
/**
 * This file contains only the ProgramSubscriber class.
 */

namespace AppBundle\EventSubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;
use AppBundle\Model\Program;

/**
 * ProgramSubscriber does post-processing after fetching a Program.
 */
class ProgramSubscriber
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /**
     * Constructor for ProgramSubscriber.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * This is automatically called by Doctrine when loading an entity,
     * or directly with EventManager::dispatchEvent().
     * @param  LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function postLoad(LifecycleEventArgs $event)
    {
        $program = $event->getEntity();

        if (!$program instanceof Program) {
            return;
        }

        // Sort the organizers alphabetically, putting the currently viewing organizer first.
        if ($this->container->get('session') && $this->container->get('session')->get('logged_in_user')) {
            $currentOrg = $this->container->get('session')->get('logged_in_user')->username;
            $program->sortOrganizers($currentOrg);
        }
    }
}
