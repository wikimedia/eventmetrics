<?php
/**
 * This file contains only the OrganizerRepository class.
 */

declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\Organizer;
use Doctrine\ORM\EntityManager;

/**
 * This class supplies and fetches data for the Organizer class.
 * @codeCoverageIgnore
 */
class OrganizerRepository extends Repository
{
    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass(): string
    {
        return Organizer::class;
    }

    /**
     * Fetch an Organizer by the given username.
     * @param string $username
     * @return Organizer
     */
    public function getOrganizerByUsername(string $username): Organizer
    {
        $userId = $this->getUserIdFromName($username);

        // Username is invalid, so just return a new Organizer
        // without a user ID so that the Form can produce errors.
        if (null === $userId) {
            return new Organizer($username);
        }

        /** @var EntityManager $em */
        $em = $this->container->get('doctrine')->getManager();
        $organizer = $em->getRepository(Organizer::class)
            ->findOneBy(['userId' => $userId]);

        if (null === $organizer) {
            $organizer = new Organizer((int)$userId);
        }

        $organizer->setUsername($username);

        return $organizer;
    }

    /**
     * Get unique metrics for all Programs created by this Organizer.
     * @param Organizer $organizer
     * @return string[]
     */
    public function getUniqueMetrics(Organizer $organizer): array
    {
        $programRepo = new ProgramRepository($this->em);
        $programRepo->setContainer($this->container);

        $metrics = [];
        foreach ($organizer->getPrograms() as $program) {
            $programMetrics = $programRepo->getUniqueMetrics($program);

            foreach ($programMetrics as $metric => $offset) {
                if (!isset($metrics[$metric])) {
                    $metrics[$metric] = $offset;
                }
            }
        }

        return $metrics;
    }
}
