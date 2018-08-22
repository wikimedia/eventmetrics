<?php
/**
 * This file contains only the UserSubscriber class.
 */

namespace AppBundle\EventSubscriber;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;
use AppBundle\Model\Organizer;
use AppBundle\Repository\Repository;
use Symfony\Component\Serializer\Tests\Model;

/**
 * UserSubscriber automatically sets the username on Organizers after the entity is loaded from the grantmetrics
 * database. Similarly it will automatically set the user_id when a Organizer is persisted.
 *
 * This class used to also do the same for Participant, but there can hundreds of these loaded at once,
 * so we instead run a single query to batch-fetch the usernames.
 */
class UserSubscriber
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /**
     * Constructor for UserSubscriber.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * This is automatically called by Doctrine when loading an entity,
     * or directly with EventManager::dispatchEvent().
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function postLoad(LifecycleEventArgs $event)
    {
        /** @var Model $entity One of the AppBundle\Model classes. */
        $entity = $event->getEntity();
        if (!($entity instanceof Organizer)) {
            return;
        }

        // Fetch and set the username on the entity.
        $repo = $this->getRepository($entity, $event);
        if ($entity->getUsername() === null) {
            $this->setUsername($entity, $repo);
        }
    }

    /**
     * Set the user ID on the Organizer.
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        /** @var Model $entity One of AppBundle\Model classes. */
        $entity = $event->getEntity();
        if (!($entity instanceof Organizer)) {
            return;
        }

        $normalized = trim(str_replace('_', ' ', $entity->getUsername()));
        $entity->setUsername(
            // Same as ucfirst but works on all locale settings. This is what MediaWiki wants.
            mb_strtoupper(mb_substr($normalized, 0, 1)).mb_substr($normalized, 1)
        );

        // Fetch and set the user ID on the entity.
        $repo = $this->getRepository($entity, $event);
        if ($entity->getUserId() === null) {
            $this->setUserId($entity, $repo);
        }
    }

    /**
     * Set the username on the Organizer for display purposes.
     * @param Organizer $entity
     * @param Repository $repo
     */
    private function setUsername($entity, $repo)
    {
        $userId = $entity->getUserId();
        $username = $repo->getUsernameFromId($userId);
        $entity->setUsername($username);
    }

    /**
     * Set the user ID on the Organizer.
     * @param Organizer $entity
     * @param Repository $repo
     */
    private function setUserId($entity, $repo)
    {
        $username = $entity->getUsername();
        $userId = $repo->getUserIdFromName($username);
        $entity->setUserId($userId);
    }

    /**
     * Get the entity and corresponding Repository, given the lifecycle event.
     * @param Organizer $entity
     * @param LifecycleEventArgs $event
     * @return Repository
     */
    private function getRepository($entity, LifecycleEventArgs $event)
    {
        /** @var EntityManager $em */
        $em = $event->getEntityManager();

        /** @var Repository $repo */
        $repo = $em->getRepository(get_class($entity));
        $repo->setContainer($this->container);

        return $repo;
    }
}
