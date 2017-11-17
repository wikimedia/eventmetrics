<?php
/**
 * This file contains only the OrganizerRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\Organizer;

/**
 * This class supplies and fetches data for the Organizer class.
 * @codeCoverageIgnore
 */
class OrganizerRepository extends Repository
{
    public function getOrganizerByUsername($username)
    {
        $ret = $this->getUserIdsFromNames([$username]);
        if (count($ret) === 0) {
            throw new \Exception("User:$username not found!");
        }

        $userId = $ret[0]['user_id'];
        $em = $this->container->get('doctrine')->getManager();
        $organizer = $em->getRepository(Organizer::class)
            ->findOneBy(['userId' => $userId]);

        if ($organizer === null) {
            $organizer = new Organizer($userId);
        }

        return $organizer;
    }
}
