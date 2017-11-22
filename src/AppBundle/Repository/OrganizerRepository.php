<?php
/**
 * This file contains only the OrganizerRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Organizer;
use Doctrine\ORM\Event\LifecycleEventArgs;

/**
 * This class supplies and fetches data for the Organizer class.
 */
class OrganizerRepository extends Repository
{
    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass()
    {
        return Organizer::class;
    }

    /**
     * Fetch an Organizer by the given username.
     * @param  string $username
     * @return Organizer
     */
    public function getOrganizerByUsername($username)
    {
        $userId = $this->getUserIdFromName($username);
        if ($userId === null) {
            // This should never happen at this point in the code,
            // so throw an Exception.
            throw new \Exception("User:$username not found!");
        }

        $em = $this->container->get('doctrine')->getManager();
        $organizer = $em->getRepository(Organizer::class)
            ->findOneBy(['userId' => $userId]);

        if ($organizer === null) {
            $organizer = new Organizer((int)$userId);
        }

        $organizer->setUsername($username);

        return $organizer;
    }

    /**
     * Create or update the given Organizer, persisting to database.
     * @param  Organizer $organizer
     * @return int The ID of the record in the database.
     */
    public function createOrUpdate(Organizer $organizer)
    {
        $em = $this->getEntityManager();

        // Invoke prepersist callback.
        $eventManager = $em->getEventManager();
        $eventArgs = new LifecycleEventArgs($organizer, $em);
        $eventManager->dispatchEvent(\Doctrine\ORM\Events::prePersist, $eventArgs);

        // Write to database.
        $conn = $em->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO organizer (org_user_id)
            VALUES (:userId)
            ON DUPLICATE KEY UPDATE
                org_id = LAST_INSERT_ID(org_id),
                org_user_id = org_user_id
        ");
        $stmt->execute([
            'userId' => $organizer->getUserId(),
        ]);
        return $conn->lastInsertId();
    }
}
