<?php declare( strict_types=1 );

namespace App\EventSubscriber;

use App\Model\Program;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * ProgramSubscriber does post-processing after fetching a Program.
 */
class ProgramSubscriber {

	/**
	 * Constructor for ProgramSubscriber.
	 * @param RequestStack $requestStack
	 */
	public function __construct( protected RequestStack $requestStack ) {
	}

	/**
	 * This is automatically called by Doctrine when loading an entity, or directly with EventManager::dispatchEvent().
	 * @param Program $program
	 */
	public function postLoad( Program $program ): void {
		if ( !$this->requestStack->getMainRequest() ) {
			// Can happen during tests.
			return;
		}
		// Sort the organizers alphabetically, putting the currently viewing organizer first.
		if ( $this->requestStack->getSession()->get( 'logged_in_user' ) ) {
			$currentOrg = $this->requestStack->getSession()->get( 'logged_in_user' )->username;
			$program->sortOrganizers( $currentOrg );
		}
	}
}
