<?php
/**
 * This file contains only the Model class.
 */

namespace AppBundle\Model;

use Exception;
use AppBundle\Repository\Repository;

/**
 * A model is any domain-side entity to be represented in the application.
 * Models know nothing of persistence, transport, or presentation.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 * Abstract class is not testable.
 * @codeCoverageIgnore
 */
abstract class Model
{
    /** @var Repository The repository for this model. */
    private $repository;

    /**
     * Set this model's data repository.
     * @param Repository $repository
     */
    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get this model's repository.
     * @return Repository A subclass of Repository.
     * @throws Exception If the repository hasn't been set yet.
     */
    public function getRepository()
    {
        if (!$this->repository instanceof Repository) {
            $class = get_class($this);

            // Attempt to autoload the repository class.
            $entity = explode('\\', $class);
            $repoClass = 'AppBundle\\Repository\\'.end($entity).'Repository';
            if (class_exists($repoClass)) {
                $this->repository = new $repoClass;
            } else {
                // Otherwise throw exception.
                $msg = sprintf('Repository for %s must be set before using.', $class);
                throw new Exception($msg);
            }
        }
        return $this->repository;
    }

    /**
     * Has a Repository been set on this Model?
     * @return bool
     */
    public function hasRepository()
    {
        return $this->repository instanceof Repository;
    }

    /**
     * Get the global user ID for the given username, based on the central auth database.
     * @param  string $username
     * @return int
     */
    public function getUserIdFromName($username)
    {
        $ret = $this->getRepository()->getUserIdsFromNames([$username]);
        if (count($ret) === 0) {
            return null;
        }
        return $ret[0]['user_id'];
    }

    /**
     * Get the username given the global user ID, based on the central auth database.
     * @param  int $userId
     * @return string
     */
    public function getNameFromUserId($userId)
    {
        $ret = $this->getRepository()->getNamesFromUserIds([$userId]);
        if (count($ret) === 0) {
            return null;
        }
        return $ret[0]['user_name'];
    }
}
