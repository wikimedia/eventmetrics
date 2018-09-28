<?php
/**
 * This file contains only the CategorySubscriber class.
 */

declare(strict_types=1);

namespace AppBundle\EventSubscriber;

use AppBundle\Model\EventCategory;
use AppBundle\Repository\EventCategoryRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Container\ContainerInterface;

/**
 * CategorySubscriber automatically sets the category ID on EventCategories before they are persisted.
 * This isn't usually needed because categories are (currently) only created through the HTML form, which has it's own
 * validation for category ID. This is here for when categories are persisted by other means.
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
     * Set the category ID on the EventCategory upon persisting.
     * @param EventCategory $category
     * @param LifecycleEventArgs $event Doctrine lifecycle event arguments.
     */
    public function prePersist(EventCategory $category, LifecycleEventArgs $event): void
    {
        if (null !== $category->getCategoryId()) {
            return;
        }

        /** @var EntityManager $em */
        $em = $event->getEntityManager();

        /** @var EventCategoryRepository $ecRepo */
        $ecRepo = $em->getRepository(EventCategory::class);
        $ecRepo->setContainer($this->container);

        $catId = $ecRepo->getCategoryId($category->getDomain(), $category->getTitle(true));

        if (is_int($catId)) {
            $category->setCategoryId($catId);
        }
    }
}
