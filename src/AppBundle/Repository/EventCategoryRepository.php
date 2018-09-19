<?php
/**
 * This file contains only the EventCategoryRepository class.
 */

declare(strict_types=1);

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
    public function getEntityClass(): string
    {
        return EventCategory::class;
    }

    /**
     * Get the IDs of pages in the given categories.
     * @param string $dbName Database name such as 'enwiki_p'.
     * @param array $titles Titles of categories to fetch from.
     * @param bool $queryBuilder Whether to return just the Doctrine query builder object.
     * @param int|null $limit Max number of pages. null for no limit, but only do this if used in a subquery.
     * @return array|QueryBuilder Page IDs or the QueryBuilder object.
     */
    public function getPagesInCategories($dbName, array $titles, bool $queryBuilder = false, ?int $limit = 20000)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['DISTINCT(cl_from)'])
            ->from("$dbName.categorylinks")
            ->join("$dbName.categorylinks", "$dbName.category", 'cl', 'cl_to = cat_title')
            ->join("$dbName.categorylinks", "$dbName.page", 'p', 'cl_from = page_id')
            ->where('page_namespace = 0')
            ->andWhere('cat_title IN (:categoryTitles)')
            ->setParameter('categoryTitles', $titles, Connection::PARAM_STR_ARRAY);

        if (is_int($limit)) {
            $rqb->setMaxResults($limit);
        }

        if ($queryBuilder) {
            return $rqb;
        }

        return $this->executeQueryBuilder($rqb)->fetchAll();
    }
}
