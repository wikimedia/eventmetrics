<?php
/**
 * This file contains only the UserSubscriber class.
 */

namespace AppBundle\EventSubscriber;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;
use AppBundle\Model\Organizer;
use AppBundle\Model\Participant;
use AppBundle\Repository\Repository;

/**
 * UserSubscriber does post-processing after fetching Organizers and Participants.
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
     * This is automatically called by Doctrine when loading an entity, or directly with EventManager::dispatchEvent().
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function postLoad(LifecycleEventArgs $event)
    {
        /** @var mixed $entity One of the AppBundle\Model classes. */
        $entity = $event->getEntity();
        if (!$this->isUserType($entity)) {
            return;
        }

        // Fetch and set the username on the entity.
        $repo = $this->getRepository($entity, $event);
        if ($entity->getUsername() === null) {
            $this->setUsername($entity, $repo);
        }
    }

    /**
     * Set the user ID on the Organizer or Participant for display purposes.
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        /** @var mixed $entity One of AppBundle\Model classes. */
        $entity = $event->getEntity();
        if (!$this->isUserType($entity)) {
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
     * Is the entity an Organizer or Participant?
     * @param mixed $entity
     * @return boolean
     */
    private function isUserType($entity)
    {
        // NOTE: Participant usernames are now loaded with a single query.
        // We'll at some point do the same for Organizers.
        return $entity instanceof Organizer;
    }

    /**
     * Set the username on the Organizer or Participant for display purposes.
     * @param Organizer|Participant $entity
     * @param Repository $repo
     */
    private function setUsername($entity, $repo)
    {
        $userId = $entity->getUserId();
        $username = $repo->getUsernameFromId($userId);
        $entity->setUsername($username);
    }

    /**
     * Set the user ID on the Organizer or Participant.
     * @param Organizer|Participant $entity
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
     * @param Organizer|Participant $entity
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
