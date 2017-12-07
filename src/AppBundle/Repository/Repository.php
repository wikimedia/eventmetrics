<?php
/**
 * This file contains only the Repository class.
 */

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManager;

/**
 * A Repository is responsible for retrieving data from wherever it lives
 * (databases, APIs, filesystems, etc.).
 * @codeCoverageIgnore
 */
abstract class Repository extends EntityRepository
{
    /** @var Container The application's DI container. */
    protected $container;

    /** @var CacheItemPoolInterface The cache. */
    protected $cache;

    /** @var LoggerInterface The log. */
    protected $log;

    /** @var Stopwatch The stopwatch for time profiling. */
    protected $stopwatch;

    /** @var Connection The connection to the grantmetrics database. */
    private $grantmetricsConnection;

    /** @var Connection The database connection to the replicas. */
    private $replicaConnection;

    /** @var Connection The CentralAuth database connection. */
    private $centralAuthConnection;

    /** @var Connection The meta database connection. */
    private $metaConnection;

    /** @var EntityManager The Doctrine entity manager. */
    protected $em;

    /**
     * Create a new Repository with a null logger.
     * Each Repository should define getEntityClass(), which gets
     * passed into the parent EntityRepository construct.
     * @param EntityManager $em The Doctrine entity manager.
     */
    public function __construct(EntityManager $em)
    {
        $metadata = $em->getClassMetadata($this->getEntityClass());
        parent::__construct($em, $metadata);

        $this->em = $em;
        $this->log = new NullLogger();
    }

    /**
     * The name of the Entity class associated with the Repository
     * (such as Program, Organizer, etc.). This must be defined
     * in every Repository class.
     * @abstract
     * @return string
     */
    abstract public function getEntityClass();

    /**
     * Set the DI container and assign the cache, log, and
     * stopwatch adapters, which are accessed via the Container.
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
        $this->cache = $container->get('cache.app');
        $this->log = $container->get('logger');
        $this->stopwatch = $container->get('debug.stopwatch');
    }

    /**
     * Get the DI container.
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get the database connection for the 'grantmetrics' database.
     * @return Connection
     */
    protected function getGrantmetricsConnection()
    {
        if (!$this->grantmetricsConnection instanceof Connection) {
            $this->grantmetricsConnection = $this->container
                ->get('doctrine')
                ->getManager('grantmetrics')
                ->getConnection();
        }
        return $this->grantmetricsConnection;
    }

    /**
     * Get the database connection for the replicas.
     * @return Connection
     */
    protected function getCentralAuthConnection()
    {
        if (!$this->centralAuthConnection instanceof Connection) {
            $this->centralAuthConnection = $this->getContainer()
                ->get('doctrine')
                ->getManager('centralauth')
                ->getConnection();
        }
        return $this->centralAuthConnection;
    }

    /**
     * Get the database connection for the replicas.
     * @return Connection
     */
    protected function getMetaConnection()
    {
        if (!$this->metaConnection instanceof Connection) {
            $this->metaConnection = $this->getContainer()
                ->get('doctrine')
                ->getManager('meta')
                ->getConnection();
        }
        return $this->metaConnection;
    }

    /**
     * Get the database connection for the replicas.
     * @return Connection
     */
    protected function getReplicaConnection()
    {
        if (!$this->replicaConnection instanceof Connection) {
            $this->replicaConnection = $this->container
                ->get('doctrine')
                ->getManager('replicas')
                ->getConnection();
        }
        return $this->replicaConnection;
    }

    /**
     * Get the global user IDs for mutiple users,
     * based on the central auth database.
     * @param  string[] $usernames Usernames to query for.
     * @return string[] with keys 'user_name' and 'user_id'.
     * FIXME: add caching.
     */
    public function getUserIdsFromNames($usernames)
    {
        $rqb = $this->getCentralAuthConnection()->createQueryBuilder();
        $rqb->select(['gu_name AS user_name', 'gu_id AS user_id'])
            ->from('globaluser')
            ->andWhere('gu_name IN (:usernames)')
            ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);
        $stmt = $rqb->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get the global user ID for the given username.
     * @param  string $username
     * @return int|null
     */
    public function getUserIdFromName($username)
    {
        $ret = $this->getUserIdsFromNames([$username]);
        return isset($ret[0]['user_id']) ? $ret[0]['user_id'] : null;
    }

    /**
     * Get the usernames given multiple global user IDs,
     * based on the central auth database.
     * @param  int[] $userIds User IDs to query for.
     * @return string[] with keys 'user_name' and 'user_id'.
     * FIXME: add caching.
     */
    public function getUsernamesFromIds($userIds)
    {
        $rqb = $this->getCentralAuthConnection()->createQueryBuilder();
        $rqb->select(['gu_name AS user_name', 'gu_id AS user_id'])
            ->from('globaluser')
            ->andWhere('gu_id IN (:userIds)')
            ->setParameter('userIds', $userIds, Connection::PARAM_INT_ARRAY);
        $stmt = $rqb->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get the username given the global user ID.
     * @param  int $userId
     * @return string|null
     */
    public function getUsernameFromId($userId)
    {
        $ret = $this->getUsernamesFromIds([$userId]);
        return isset($ret[0]['user_name']) ? $ret[0]['user_name'] : null;
    }
}
