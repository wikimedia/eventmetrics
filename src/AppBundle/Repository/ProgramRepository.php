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

    /**
     * Create or update the given Program, persisting to database.
     * @param  Program $program
     * @return int The ID of the record in the database.
     */
    public function createOrUpdate(Program $program)
    {
        $programId = $program->getId();

        if ($programId) {
            $this->update($program);
        } else {
            $programId = $this->create($program);
        }

        // Update data for the organizers, including the join table.
        $this->updateProgramOrganizers((int)$programId, $program->getOrganizers());

        return $programId;
    }

    /**
     * Create a new record for the give program in the database.
     * @param  Program $program
     * @return int The program_id.
     */
    public function create(Program $program)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO program (program_title)
            VALUES(:title)
        ");
        $stmt->execute(['title' => $program->getTitle()]);
        return $this->conn->lastInsertId();
    }

    /**
     * Persist the given program to the database.
     * @param  Program $program
     */
    public function update(Program $program)
    {
        $programId = $program->getId();
        $stmt = $this->conn->prepare("
            UPDATE program
            SET program_title = :title
            WHERE program_id = $programId
        ");
        $stmt->execute(['title' => $program->getTitle()]);
    }

    /**
     * Create or update the Organizers of the program, and make sure a
     * corresponding recording exists in the organizers_programs join table.
     * @param  int $programId ID of the program, fetched from last insert.
     * @param  Organizer[] $organizers Organizers of the program.
     */
    private function updateProgramOrganizers($programId, $organizers)
    {
        $organizerRepo = $this->em->getRepository(Organizer::class);

        foreach ($organizers as $organizer) {
            // Make sure the organizer has been created.
            $orgId = $organizerRepo->createOrUpdate($organizer);

            // Add reference to join table.
            $this->conn->query("
                INSERT INTO organizers_programs (program_id, org_id)
                VALUES ($programId, $orgId)
                ON DUPLICATE KEY UPDATE
                    program_id = program_id,
                    org_id = org_id
            ");
        }
    }
}
