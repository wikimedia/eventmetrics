<?php declare( strict_types=1 );

namespace App\Twig;

use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;

/**
 * The parent class for all of the Twig extensions, in order to centralize the i18n set-up.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
abstract class Extension extends AbstractExtension {

	/**
	 * Extension constructor.
	 * @param RequestStack $requestStack The request stack.
	 * @param Intuition $intuition
	 */
	public function __construct(
		protected RequestStack $requestStack,
		protected Intuition $intuition
	) {
	}

	/**
	 * Shorthand to get the current request from the request stack.
	 * @return Request
	 * There is no request stack in the tests.
	 * @codeCoverageIgnore
	 */
	protected function getCurrentRequest(): Request {
		return $this->requestStack->getCurrentRequest();
	}
}
