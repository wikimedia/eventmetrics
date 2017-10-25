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
     *
     * @param Repository $repository
     */
    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get this model's repository.
     *
     * @return Repository A subclass of Repository.
     * @throws Exception If the repository hasn't been set yet.
     */
    public function getRepository()
    {
        if (!$this->repository instanceof Repository) {
            $msg = sprintf('Repository for %s must be set before using.', get_class($this));
            throw new Exception($msg);
        }
        return $this->repository;
    }
}
