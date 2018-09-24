<?php
/**
 * This file contains only the ProgramSubscriber class.
 */

declare(strict_types=1);

namespace AppBundle\EventSubscriber;

use AppBundle\Model\Program;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
     * This is automatically called by Doctrine when loading an entity, or directly with EventManager::dispatchEvent().
     * @param Program $program
     */
    public function postLoad(Program $program): void
    {
        // Sort the organizers alphabetically, putting the currently viewing organizer first.
        if ($this->container->get('session') && $this->container->get('session')->get('logged_in_user')) {
            $currentOrg = $this->container->get('session')->get('logged_in_user')->username;
            $program->sortOrganizers($currentOrg);
        }
    }
}
