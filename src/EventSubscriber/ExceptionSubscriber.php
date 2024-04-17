<?php declare( strict_types=1 );

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;
use Twig\Environment;
use Twig\Error\RuntimeError;

/**
 * A ExceptionSubscriber ensures Twig exceptions are properly
 * handled, so that a friendly error page is shown to the user.
 */
class ExceptionSubscriber {

	/**
	 * Constructor for the ExceptionListener.
	 * @param Environment $twig
	 * @param LoggerInterface $logger
	 * @param string $environment
	 */
	public function __construct(
		private readonly Environment $twig,
		private readonly LoggerInterface $logger,
		private readonly string $environment = 'prod'
	) {
	}

	/**
	 * Capture the exception, check if it's a Twig error and if so
	 * throw the previous exception, which should be more meaningful.
	 * @param ExceptionEvent $event
	 * @throws Throwable
	 */
	public function onKernelException( ExceptionEvent $event ): void {
		$exception = $event->getThrowable();

		// We only care about the previous (original) exception,
		// not the one Twig put on top of it.
		$prevException = $exception->getPrevious();

		if ( !( $exception instanceof RuntimeError && $prevException !== null ) ) {
			return;
		}

		if ( $this->environment !== 'prod' ) {
			throw $prevException;
		}

		// Log the exception, since we're handling it and it won't automatically be logged.
		$file = explode( '/', $prevException->getFile() );
		$this->logger->error(
			'>>> CRITICAL (\'' . $prevException->getMessage() . '\' - ' .
			end( $file ) . ' - line ' . $prevException->getLine() . ')'
		);

		$response = new Response(
			$this->twig->render( 'TwigBundle:Exception:error.html.twig', [
				'status_code' => 500,
				'status_text' => 'Internal Server Error',
				'exception' => $prevException,
			] )
		);

		// sends the modified response object to the event
		$event->setResponse( $response );
	}
}
