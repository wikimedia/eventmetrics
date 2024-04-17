<?php declare( strict_types=1 );

namespace App\Model\Traits;

// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;

/**
 * The TitleUserTrait refactors out common methods between Program and Event. These methods pertain to titles of the
 * entity, and the associated user class (Organizer or Participant).
 */
trait TitleUserTrait {
	/**
	 * The class name of users associated with Events, either 'Organizer' or 'Participant'.
	 * @abstract
	 * @return string
	 */
	abstract public function getUserClassName(): string;

	/*********
	 * TITLE *
	 *********/

	/**
	 * Get the title of this Program.
	 * @return string|null
	 */
	public function getTitle(): ?string {
		return $this->title;
	}

	/**
	 * Get the display variant of the program title.
	 * @return string
	 */
	public function getDisplayTitle(): string {
		return str_replace( '_', ' ', $this->title ?? '' );
	}

	/**
	 * Set the title of this Program.
	 * @param string|null $title
	 */
	public function setTitle( ?string $title ): void {
		if ( $title === null ) {
			$this->title = null;
			return;
		}
		// Remove 4-byte unicode characters (replace with the "replacement character" �).
		// Kudos https://stackoverflow.com/a/24672780
		// More info http://unicode.org/reports/tr36/#Deletion_of_Noncharacters
		$title = preg_replace( '/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $title );
		// Use underscores instead of spaces.
		$this->title = str_replace( ' ', '_', trim( $title ) );
	}

	/**
	 * Validates that the title is not just a number (as these would be assumed to be IDs).
	 * @Assert\Callback
	 * @param ExecutionContext $context
	 */
	public function validateNonNumerical( ExecutionContext $context ): void {
		if ( ctype_digit( $this->title ) ) {
			$context->buildViolation( 'error-title-numeric' )
				->atPath( 'title' )
				->addViolation();
		}
	}

	/**
	 * Validates that the title does not contain invalid characters.
	 * @Assert\Callback
	 * @param ExecutionContext $context Supplied by Symfony.
	 */
	public function validateCharacters( ExecutionContext $context ): void {
		if ( preg_match( '/[\/]/', $this->title ) === 1 ) {
			$context->buildViolation( 'error-title-invalid-chars' )
				->setParameter( '0', '<code>/</code>' )
				->atPath( 'title' )
				->addViolation();
		}
	}

	/*****************************
	 * ORGANIZERS / PARTICIPANTS *
	 *****************************/

	/**
	 * Validates that the model's users (Organizers or Participants) have user IDs.
	 * @Assert\Callback
	 * @param ExecutionContext $context Supplied by Symfony.
	 */
	public function validateUsers( ExecutionContext $context ): void {
		$userIds = $this->{'get' . $this->getUserClassName() . 'Ids'}();
		$numEmpty = count( $userIds ) - count( array_filter( $userIds ) );
		if ( $numEmpty > 0 ) {
			$context->buildViolation( 'error-usernames' )
				->setParameter( '0', (string)$numEmpty )
				->atPath( strtolower( $this->getUserClassName() . 's' ) )
				->addViolation();
		}
	}
}
