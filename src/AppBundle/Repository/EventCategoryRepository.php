<?php
/**
 * This file contains only the EventCategoryRepository class.
 */

declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;

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
     * @param int[] $ids IDs of categories to fetch from.
     * @param int|null $limit Max number of pages. null for no limit, but only do this if used in a subquery.
     * @return int[]
     */
    public function getPagesInCategories(string $dbName, array $ids, ?int $limit = 20000): array
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select(['DISTINCT(cl_from)'])
            ->from("$dbName.categorylinks")
            ->join("$dbName.categorylinks", "$dbName.category", 'cl', 'cl_to = cat_title')
            ->join("$dbName.categorylinks", "$dbName.page", 'p', 'cl_from = page_id')
            ->where('page_namespace = 0')
            ->andWhere('page_is_redirect = 0')
            // IDs are validated as integers in the model, and hence safe to put directly in the SQL.
            ->andWhere('cat_id IN ('.implode(',', $ids).')');

        if (is_int($limit)) {
            $rqb->setMaxResults($limit);
        }

        $result = $this->executeQueryBuilder($rqb)->fetchAll(\PDO::FETCH_COLUMN);
        return $result ? array_map('intval', $result) : [];
    }

    /**
     * Fetch the ID of the given category from the replicas.
     * @param string $domain
     * @param string $title
     * @return int|null Null if nonexistent.
     */
    public function getCategoryId(string $domain, string $title): ?int
    {
        // If no domain provided, no category can have an ID.
        if (empty($domain)) {
            return null;
        }

        // Get the database name.
        $ewRepo = $this->em->getRepository(EventWiki::class);
        $ewRepo->setContainer($this->container);
        $dbName = $ewRepo->getDbNameFromDomain($domain);

        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select('cat_id')
            ->from("$dbName.category")
            ->where('cat_title = :title')
            ->setParameter('title', str_replace(' ', '_', ucfirst($title)));
        $id = $this->executeQueryBuilder($rqb)->fetchColumn();

        return $id ? (int)$id : null;
    }
}
