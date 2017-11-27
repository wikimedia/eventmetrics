<?php
/**
 * This file contains only the ProgramRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Program;
use AppBundle\Model\Organizer;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

/**
 * This class supplies and fetches data for the Program class.
 * @codeCoverageIgnore
 */
class ProgramRepository extends Repository
{
    /** @var Connection The connection to the database. */
    protected $conn;

    /**
     * Constructor for the ProgramRepository.
     * @param EntityManager $em The Doctrine entity manager.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em);
        $this->conn = $em->getConnection();
    }

    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass()
    {
        return Program::class;
    }
}
