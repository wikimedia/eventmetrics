<?php
/**
 * This file contains only the EventCategoryRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\EventCategory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

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
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param int[] $categoryIds
     * @param bool $queryBuilder Whether to return just the Doctrine query builder object.
     * @return array|QueryBuilder Array with keys 'cat_title' and 'cat_id', or the QueryBuilder object.
     */
    public function getCategoryNamesFromIds($dbName, array $categoryIds, $queryBuilder = false)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['cat_title', 'cat_id'])
            ->from("$dbName.category")
            ->andWhere('cat_id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds, Connection::PARAM_INT_ARRAY);

        if ($queryBuilder) {
            return $rqb;
        }

        // false means do not set a max query time. Here it's really fast,
        // and setting the query timeout actually slows it down.
        return $this->executeQueryBuilder($rqb, false)->fetchAll();
    }

    /**
     * Get the category name given the ID.
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param $categoryId
     * @return string|null Null if not found.
     */
    public function getCategoryNameFromId($dbName, $categoryId)
    {
        $ret = $this->getCategoryNamesFromIds($dbName, [$categoryId]);
        return isset($ret[0]['cat_title']) ? $ret[0]['cat_title'] : null;
    }

    /**
     * Fetch the ids of the categories with the given titles.
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param int[] $categoryNames
     * @return array with keys 'cat_title' and 'cat_id'.
     */
    public function getCategoryIdsFromNames($dbName, array $categoryNames)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['cat_title', 'cat_id'])
            ->from("$dbName.category")
            ->andWhere('cat_title IN (:categoryTitles)')
            ->setParameter('categoryTitles', $categoryNames, Connection::PARAM_STR_ARRAY);
        // false means do not set a max query time. Here it's really fast,
        // and setting the query timeout actually slows it down.
        return $this->executeQueryBuilder($rqb, false)->fetchAll();
    }

    /**
     * Get the category ID given the name.
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param $categoryName
     * @return string|null Null if not found.
     */
    public function getCategoryIdFromName($dbName, $categoryName)
    {
        $ret = $this->getCategoryIdsFromNames($dbName, [$categoryName]);
        return isset($ret[0]['cat_id']) ? $ret[0]['cat_id'] : null;
    }

    /**
     * Get the IDs of pages in the given categories.
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param array $categoryIds IDs of categories to fetch from.
     * @param bool $queryBuilder Whether to return just the Doctrine query builder object.
     * @param int|null $limit Max number of pages. null for no limit, but only do this if used in a subquery.
     * @return array|QueryBuilder Page IDs or the QueryBuilder object.
     */
    public function getPagesInCategories($dbName, array $categoryIds, $queryBuilder = false, $limit = 20000)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['DISTINCT(cl_from)'])
            ->from("$dbName.categorylinks")
            ->join("$dbName.categorylinks", "$dbName.category", 'cl', 'cl_to = cat_title')
            ->join("$dbName.categorylinks", "$dbName.page", 'p', 'cl_from = page_id')
            ->where('page_namespace = 0')
            ->andWhere('cat_id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds, Connection::PARAM_INT_ARRAY);

        if (is_int($limit)) {
            $rqb->setMaxResults($limit);
        }

        if ($queryBuilder) {
            return $rqb;
        }

        return $this->executeQueryBuilder($rqb)->fetchAll();
    }
}
