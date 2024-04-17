<?php declare( strict_types=1 );

namespace App\Twig;

use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

/**
 * Various Twig functions and filters.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class AppExtension extends Extension {

	/** @var string[] Event Metrics users with administrative rights. */
	protected array $appAdmins;

	/**
	 * @param RequestStack $requestStack
	 * @param Intuition $intuition
	 * @param string $appAdmins
	 */
	public function __construct(
		RequestStack $requestStack,
		Intuition $intuition,
		string $appAdmins
	) {
		$this->appAdmins = explode( '|', $appAdmins );
		parent::__construct( $requestStack, $intuition );
	}

	/**
	 * Get the name of this extension.
	 * @return string
	 * @codeCoverageIgnore
	 */
	public function getName(): string {
		return 'app_extension';
	}

	/*************
	 * FUNCTIONS *
	 *************/

	/**
	 * Get all functions that this class provides.
	 * @return TwigFunction[]
	 */
	public function getFunctions(): array {
		return [
			new TwigFunction( 'is_admin', [ $this, 'isAdmin' ] ),
			new TwigFunction( 'msg_if_exists', [ $this, 'msgIfExists' ] ),
		];
	}

	/**
	 * Is the logged in user an admin?
	 * @return bool
	 * This is tested via EventControllerTest, validating delete buttons have the correct CSS
	 * class, but for some reason the clover system doesn't detect that this bit of code was ran.
	 * @codeCoverageIgnore
	 */
	public function isAdmin(): bool {
		return in_array(
			$this->requestStack->getSession()->get( 'logged_in_user' )->username,
			$this->appAdmins
		);
	}
}
