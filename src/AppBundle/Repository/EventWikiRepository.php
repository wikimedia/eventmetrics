<?php
/**
 * This file contains only the EventWikiRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\EventWiki;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

/**
 * This class supplies and fetches data for the EventWiki class.
 * @codeCoverageIgnore
 */
class EventWikiRepository extends Repository
{
    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass()
    {
        return EventWiki::class;
    }

    /**
     * Get the wiki's domain name without the .org given a database name or domain.
     * @param  string $value
     * @return string|null Null if no wiki was found.
     */
    public function getDomainFromEventWikiInput($value)
    {
        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(['dbname, url'])
            ->from('wiki')
            ->where($rqb->expr()->eq('dbname', ':project'))
            ->orwhere($rqb->expr()->like('url', ':projectUrl'))
            ->orwhere($rqb->expr()->like('url', ':projectUrl2'))
            ->setParameter('project', $value)
            ->setParameter('projectUrl', "https://$value")
            ->setParameter('projectUrl2', "https://$value.org");
        $stmt = $rqb->execute();
        $ret = $stmt->fetch();

        $matches = [];
        preg_match('/^https?\:\/\/(.*)\.org$/', $ret['url'], $matches);
        $domain = isset($matches[1]) ? str_replace('www.', '', $matches[1]) : null;

        return $domain;
    }
}
