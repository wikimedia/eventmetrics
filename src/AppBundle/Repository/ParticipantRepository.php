<?php
/**
 * This file contains only the ParticipantRepository class.
 */

declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\Participant;

/**
 * This class supplies and fetches data for the Participant class.
 * @codeCoverageIgnore
 */
class ParticipantRepository extends Repository
{
    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass(): string
    {
        return Participant::class;
    }

    /**
     * Fetch participant SQL rows.
     * @param string[] $usernames
     * @return string[] with keys 'user_name' and 'user_id'. 'user_id' is
     *                  null if no record was found in `centralauth_p.globaluser`.
     */
    public function getRowsFromUsernames(array $usernames): array
    {
        $userRows = $this->getUserIdsFromNames($usernames);

        // Usernames for which an account exists in CentralAuth.
        $foundUsernames = array_column($userRows, 'user_name');

        // Usernames that were requested but not found in CentralAuth.
        $notFoundUsernames = array_diff($usernames, $foundUsernames);

        // Back fill $userRows with the missing usernames,
        // using null as their `user_id`.
        foreach ($notFoundUsernames as $username) {
            $userRows[] = [
                'user_name' => $username,
                'user_id' => null,
            ];
        }

        return $userRows;
    }
}
