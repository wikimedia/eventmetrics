<?php declare( strict_types=1 );

namespace App\Repository;

use App\Model\Job;

class JobRepository extends Repository {
	/**
	 * @inheritDoc
	 */
	public function getEntityClass(): string {
		return Job::class;
	}
}
