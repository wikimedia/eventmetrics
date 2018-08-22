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
use Symfony\Component\Serializer\Tests\Model;

/**
 * CategorySubscriber automatically sets the title on categories from the replicas, after the Entity is loaded from
 * the grantmetrics database. Similarly it will automatically set the category_id when an EventCategory is persisted.
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
        /** @var Model $entity One of the AppBundle\Model classes. */
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
     * Set the category ID on the EventCategory for display purposes.
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        /** @var Model $entity One of the AppBundle\Model classes. */
        $entity = $event->getEntity();
        if (!($entity instanceof EventCategory) || is_int($entity->getCategoryId())) {
            return;
        }

        /** @var EventWiki $wiki */
        $wiki = $entity->getWiki();

        // The dbName may already be set from the postLoad hook. This check just prevents redundant querying.
        if (null === $wiki->getDbName()) {
            /** @var EventWikiRepository $repo */
            $ewRepo = $this->getRepository($event, EventWiki::class);
            $wiki->setDbName($ewRepo->getDbName($wiki));
        }

        $categoryTitle = str_replace(' ', '_', trim($entity->getTitle()));
        $categoryId = $this->getRepository($event)
            ->getCategoryIdFromName($wiki->getDbName(), $categoryTitle);
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
