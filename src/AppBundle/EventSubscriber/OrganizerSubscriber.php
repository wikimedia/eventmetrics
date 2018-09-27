<?php
/**
 * This file contains only the OrganizerSubscriber class.
 */

declare(strict_types=1);

namespace AppBundle\EventSubscriber;

use AppBundle\Model\Organizer;
use AppBundle\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;

/**
 * OrganizerSubscriber automatically sets the username on Organizers after the entity is loaded from the grantmetrics
 * database. Similarly it will automatically set the user_id when a Organizer is persisted.
 *
 * This class used to also do the same for Participant, but there can hundreds of these loaded at once,
 * so we instead run a single query to batch-fetch the usernames.
 */
class OrganizerSubscriber
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /** @var OrganizerRepository Repository for the Organizer class. */
    private $repo;

    /**
     * Constructor for UserSubscriber.
     * @param ContainerInterface $container
     * @param EntityManagerInterface $em
     */
    public function __construct(ContainerInterface $container, EntityManagerInterface $em)
    {
        $this->container = $container;
        $this->repo = $em->getRepository(Organizer::class);
        $this->repo->setContainer($this->container);
    }

    /**
     * This is automatically called by Doctrine when loading an entity,
     * or directly with EventManager::dispatchEvent().
     * @param Organizer $organizer
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function postLoad(Organizer $organizer, LifecycleEventArgs $event): void
    {
        // Fetch and set the username on the entity.
        if (null === $organizer->getUsername()) {
            $userId = $organizer->getUserId();
            $username = $this->repo->getUsernameFromId($userId);
            $organizer->setUsername($username);
        }
    }

    /**
     * Set the user ID on the Organizer.
     * @param Organizer $organizer
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(Organizer $organizer, LifecycleEventArgs $event): void
    {
        $normalized = trim(str_replace('_', ' ', $organizer->getUsername()));
        $organizer->setUsername(
            // Same as ucfirst but works on all locale settings. This is what MediaWiki wants.
            mb_strtoupper(mb_substr($normalized, 0, 1)).mb_substr($normalized, 1)
        );

        // Fetch and set the user ID on the entity.
        if (null === $organizer->getUserId()) {
            $userId = $this->repo->getUserIdFromName($organizer->getUsername());
            $organizer->setUserId($userId);
        }
    }
}
