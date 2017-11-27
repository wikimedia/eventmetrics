<?php
/**
 * This file contains only the OrganizerRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Organizer;
use Doctrine\ORM\Event\LifecycleEventArgs;

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

        // Username is invalid, so just return a new Organizer
        // without a user ID so that the Form can produce errors.
        if ($userId === null) {
            return new Organizer($username);
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
}
