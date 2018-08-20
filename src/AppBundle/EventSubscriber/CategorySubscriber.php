<?php
/**
 * This file contains only the CategorySubscriber class.
 */

namespace AppBundle\EventSubscriber;

use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;
use AppBundle\Repository\EventCategoryRepository;
use AppBundle\Repository\EventWikiRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;

/**
 * CategorySubscriber does post-processing after fetching EventCategories.
 */
class CategorySubscriber
{
    /** @var ContainerInterface The application's container interface. */
    private $container;

    /**
     * Constructor for CategorySubscriber.
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
        if (!($entity instanceof EventCategory)) {
            return;
        }

        /** @var EventWikiRepository $repo */
        $ewRepo = $this->getRepository($event, EventWiki::class);

        /** @var EventWiki $ew */
        $ew = $entity->getWiki();
        $ew->setDbName($ewRepo->getDbName($ew));

        $title = $this->getRepository($event)
            ->getCategoryNameFromId($ew->getDbName(), $entity->getCategoryId());
        $entity->setTitle(str_replace('_', ' ', $title));
    }

    /**
     * Set the user ID on the Organizer or Participant for display purposes.
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        /** @var mixed $entity One of the AppBundle\Model classes. */
        $entity = $event->getEntity();
        if (!($entity instanceof EventCategory)) {
            return;
        }

        // FIXME: maybe dbName is already set from the postLoad?
        $categoryId = $this->getRepository($event)
            ->getCategoryIdFromName($entity->getWiki()->getDbName(), $entity->getTitle());
        $entity->setCategoryId($categoryId);
    }

    /**
     * Get the entity and corresponding Repository, given the lifecycle event.
     * @param LifecycleEventArgs $event
     * @param string $class Which class to get a Repository for.
     * @return EventCategoryRepository|EventWikiRepository
     */
    private function getRepository(LifecycleEventArgs $event, $class = EventCategory::class)
    {
        /** @var EntityManager $em */
        $em = $event->getEntityManager();

        /** @var EventCategoryRepository $repo */
        $repo = $em->getRepository($class);
        $repo->setContainer($this->container);

        return $repo;
    }
}
