<?php declare( strict_types=1 );

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Krinkle\Intuition\Intuition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This is the parent Controller for all controllers in this application.
 */
abstract class Controller extends AbstractController {

	/** @var Request The request object. */
	protected Request $request;

	/**
	 * Constructor for the abstract Controller.
	 * @param RequestStack $requestStack
	 * @param EntityManagerInterface $em
	 * @param Intuition $intuition
	 */
	public function __construct(
		protected RequestStack $requestStack,
		protected EntityManagerInterface $em,
		protected Intuition $intuition
	) {
		$this->request = $requestStack->getCurrentRequest();
	}

	/**
	 * Add a flash message.
	 * @param string $type
	 * @param string $messageName
	 * @param array $vars
	 */
	public function addFlashMessage( string $type, string $messageName, array $vars = [] ): void {
		$options = [
			'domain' => 'eventmetrics',
			'variables' => $vars,
		];
		$message = $this->intuition->msg( $messageName, $options );
		// @phan-suppress-next-line PhanUndeclaredMethod
		$this->requestStack->getSession()->getFlashBag()->add( $type, $message );
	}
}
