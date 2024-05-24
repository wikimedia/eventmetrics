<?php declare( strict_types=1 );

namespace App\EventSubscriber;

use App\Model\Organizer;
use App\Repository\OrganizerRepository;

/**
 * OrganizerSubscriber automatically sets the username on Organizers after the entity is loaded from the eventmetrics
 * database. Similarly it will automatically set the user_id when a Organizer is persisted.
 *
 * This class used to also do the same for Participant, but there can hundreds of these loaded at once,
 * so we instead run a single query to batch-fetch the usernames.
 */
class OrganizerSubscriber {

	/**
	 * Constructor for UserSubscriber.
	 * @param OrganizerRepository $repo
	 */
	public function __construct( private readonly OrganizerRepository $repo ) {
	}

	/**
	 * This is automatically called by Doctrine when loading an entity,
	 * or directly with EventManager::dispatchEvent().
	 * @param Organizer $organizer
	 */
	public function postLoad( Organizer $organizer ): void {
		// Fetch and set the username on the entity.
		if ( $organizer->getUsername() === null ) {
			$userId = $organizer->getUserId();
			$username = $this->repo->getUsernameFromId( $userId );
			$organizer->setUsername( $username );
		}
	}

	/**
	 * Set the user ID on the Organizer.
	 * @param Organizer $organizer
	 */
	public function prePersist( Organizer $organizer ): void {
		$normalized = trim( str_replace( '_', ' ', $organizer->getUsername() ) );
		$organizer->setUsername(
			// Same as ucfirst but works on all locale settings. This is what MediaWiki wants.
			mb_strtoupper( mb_substr( $normalized, 0, 1 ) ) . mb_substr( $normalized, 1 )
		);

		// Fetch and set the user ID on the entity.
		if ( $organizer->getUserId() === null ) {
			$userId = $this->repo->getUserIdFromName( $organizer->getUsername() );
			$organizer->setUserId( $userId );
		}
	}
}
