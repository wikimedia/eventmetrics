<?php declare( strict_types=1 );

namespace App\Repository;

use App\Model\EventWikiStat;

class EventWikiStatRepository extends Repository {

	/**
	 * @inheritDoc
	 */
	public function getEntityClass(): string {
		return EventWikiStat::class;
	}
}
