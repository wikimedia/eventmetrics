<?php
/**
 * This file contains only the EventCategoryRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\EventCategory;
use Doctrine\DBAL\Connection;

/**
 * An EventCategoryRepository supplies and fetches data for the EventCategory class.
 * @codeCoverageIgnore
 */
class EventCategoryRepository extends Repository
{
    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass()
    {
        return EventCategory::class;
    }

    /**
     * Fetch the names of the given categories.
     * @param int[] $categoryIds
     * @return array with keys 'cat_title' and 'cat_id'.
     */
    public function getCategoryNamesFromIds(array $categoryIds)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['cat_title', 'cat_id'])
            ->from('category')
            ->andWhere('cat_id IN (:ids)')
            ->setParameter('ids', $categoryIds, Connection::PARAM_INT_ARRAY);
        // false means do not set a max query time. Here it's really fast,
        // and setting the query timeout actually slows it down.
        return $this->executeQueryBuilder($rqb, false)->fetchAll();
    }

    /**
     * Get the category name given the ID.
     * @param $categoryId
     * @return string|null Null if not found.
     */
    public function getCategoryNameFromId($categoryId)
    {
        $ret = $this->getUsernamesFromIds([$categoryId]);
        return isset($ret[0]['cat_title']) ? $ret[0]['cat_title'] : null;
    }

    /**
     * Fetch the ids of the categories with the given titles.
     * @param int[] $categoryNames
     * @return array with keys 'cat_title' and 'cat_id'.
     */
    public function getCategoryIdsFromNames(array $categoryNames)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['cat_title', 'cat_id'])
            ->from('category')
            ->andWhere('cat_title IN (:titles)')
            ->setParameter('titles', $categoryNames, Connection::PARAM_STR_ARRAY);
        // false means do not set a max query time. Here it's really fast,
        // and setting the query timeout actually slows it down.
        return $this->executeQueryBuilder($rqb, false)->fetchAll();
    }

    /**
     * Get the category ID given the name.
     * @param $categoryName
     * @return string|null Null if not found.
     */
    public function getCategoryIdFromName($categoryName)
    {
        $ret = $this->getUsernamesFromIds([$categoryName]);
        return isset($ret[0]['cat_id']) ? $ret[0]['cat_id'] : null;
    }
}
