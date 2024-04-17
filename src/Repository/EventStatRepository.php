<?php declare( strict_types=1 );

namespace App\Repository;

use App\Model\EventStat;

class EventStatRepository extends Repository {
	/**
	 * @inheritDoc
	 */
	public function getEntityClass(): string {
		return EventStat::class;
	}
}
