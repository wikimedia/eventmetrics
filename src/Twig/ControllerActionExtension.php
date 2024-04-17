<?php declare( strict_types=1 );

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension that provides convient methods to get the names
 * of the current controller and action.
 */
class ControllerActionExtension extends AbstractExtension {

	/**
	 * Extension constructor.
	 * @param RequestStack $requestStack The request stack.
	 */
	public function __construct( protected RequestStack $requestStack ) {
	}

	/**
	 * Get the name of this extension.
	 * @return string
	 * @codeCoverageIgnore
	 */
	public function getName(): string {
		return 'controller_action_twig_extension';
	}

	/**
	 * Get all functions that this class provides.
	 * @return TwigFunction[]
	 */
	public function getFunctions(): array {
		return [
			new TwigFunction( 'getControllerName', [ $this, 'getControllerName' ] ),
			new TwigFunction( 'getActionName', [ $this, 'getActionName' ] ),
		];
	}

	/**
	 * Get current controller name.
	 * @return string|null
	 * There is no request stack in unit tests.
	 * @codeCoverageIgnore
	 */
	public function getControllerName(): ?string {
		$request = $this->requestStack->getCurrentRequest();

		if ( $request !== null ) {
			$pattern = "#Controller\\\([a-zA-Z]*)Controller#";
			$matches = [];
			preg_match( $pattern, $request->get( '_controller' ), $matches );

			return strtolower( $matches[1] );
		}

		return null;
	}

	/**
	 * Get current action name.
	 * @return string|null
	 * There is no request stack in unit tests.
	 * @codeCoverageIgnore
	 */
	public function getActionName(): ?string {
		$request = $this->requestStack->getCurrentRequest();

		if ( $request !== null ) {
			$pattern = "#::([a-zA-Z]*)Action#";
			$matches = [];
			preg_match( $pattern, $request->get( '_controller' ), $matches );

			return $matches[1];
		}

		return null;
	}
}
