<?php
/**
 * This file contains only the UserSubscriber class.
 */

namespace AppBundle\EventSubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;
use AppBundle\Model\Organizer;
use AppBundle\Model\Participant;
use AppBundle\Repository\Repository;

/**
 * UserSubscriber does post-processing after
 * fetching Organizers and Participants.
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
     * @param  LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function postLoad(LifecycleEventArgs $event)
    {
        list($entity, $repo) = $this->getEntityAndRepo($event);
        if ($this->isUserType($entity) && $entity->getUsername() === null) {
            $this->setUsername($entity, $repo);
        }
    }

    /**
     * Set the username on the Organizer or Participant
     * for display purposes.
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        list($entity, $repo) = $this->getEntityAndRepo($event);
        if ($this->isUserType($entity) && $entity->getUserId() === null) {
            $this->setUserId($entity, $repo);
        }
    }

    /**
     * Is the entity an Organizer or Participant?
     * @param  mixed $entity
     * @return boolean
     */
    private function isUserType($entity)
    {
        return $entity instanceof Organizer || $entity instanceof Participant;
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
     * Get the entity and corresponding repository, given the lifecycle event.
     * @param  LifecycleEventArgs $event
     * @return mixed[]
     */
    private function getEntityAndRepo(LifecycleEventArgs $event)
    {
        /** @var Entity */
        $entity = $event->getEntity();

        /** @var EntityManager */
        $em = $event->getEntityManager();

        /** @var Repository */
        $repo = $em->getRepository(get_class($entity));
        $repo->setContainer($this->container);

        return [$entity, $repo];
    }
}
