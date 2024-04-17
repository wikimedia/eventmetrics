<?php declare( strict_types=1 );

namespace App\Controller;

use App\Model\Event;
use App\Model\Organizer;
use App\Model\Program;
use App\Repository\EventRepository;
use App\Repository\OrganizerRepository;
use App\Repository\ProgramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The EntityController sets class-level properties and
 * provides methods shared amongst the ProgramController,
 * EventController, and EventDataController.
 * @abstract
 */
abstract class EntityController extends Controller {

	/** @var ValidatorInterface Used when manually validating Models, as opposed to using Symfony Forms. */
	protected ValidatorInterface $validator;

	/** @var Program|null The Program being requested. */
	protected ?Program $program;

	/** @var Event|null The Event being requested. */
	protected ?Event $event;

	/** @var array Event Metric users with administrative rights. */
	protected array $appAdmins;

	/**
	 * Constructor for the abstract EntityController.
	 * @param RequestStack $requestStack
	 * @param EntityManagerInterface $em
	 * @param Intuition $intuition
	 * @param ProgramRepository $programRepo
	 * @param EventRepository $eventRepo
	 * @param OrganizerRepository $organizerRepo
	 * @param RouterInterface $router
	 * @param string $appAdmins
	 */
	public function __construct(
		RequestStack $requestStack,
		EntityManagerInterface $em,
		Intuition $intuition,
		protected ProgramRepository $programRepo,
		protected EventRepository $eventRepo,
		protected OrganizerRepository $organizerRepo,
		protected RouterInterface $router,
		string $appAdmins
	) {
		parent::__construct( $requestStack, $em, $intuition );
		$this->appAdmins = explode( '|', $appAdmins );
		$this->validateUser();
		$this->setProgramAndEvent();
		$this->validateOrganizer();
	}

	/**
	 * Service injection point, configured in services.yaml
	 * @param ValidatorInterface $validator
	 * @codeCoverageIgnore
	 */
	public function setValidator( ValidatorInterface $validator ): void {
		$this->validator = $validator;
	}

	/**
	 * Check the request and if there are parameters for eventId or programId,
	 * find and set class properties for the corresponding entity.
	 */
	private function setProgramAndEvent(): void {
		$this->setProgram();
		$this->setEvent();
	}

	/**
	 * Get the Program associated with the Request.
	 * @return Program
	 */
	public function getProgram(): Program {
		return $this->program;
	}

	/**
	 * Check the request and if the programId is set, find and set $this->program with the corresponding entity.
	 * @throws NotFoundHttpException
	 */
	private function setProgram(): void {
		$programId = $this->request->get( 'programId' );

		if ( !$programId ) {
			return;
		}

		// Check if the programId parameter is an integer, which we will assume to be the ID and not title.
		if ( ctype_digit( (string)$programId ) ) {
			$this->program = $this->programRepo->findOneBy( [ 'id' => $programId ] );
		} else {
			// Accept program title for backwards-compatibility.
			$this->program = $this->programRepo->findOneBy( [ 'title' => $programId ] );
		}

		if ( !is_a( $this->program, Program::class ) ) {
			throw new NotFoundHttpException( 'error-not-found' );
		}
	}

	/**
	 * Get the Event associated with the Request.
	 * @return Event
	 */
	public function getEvent(): Event {
		return $this->event;
	}

	/**
	 * Check the request and if the eventId is set, find and set $this->event with the corresponding entity.
	 * @throws NotFoundHttpException
	 */
	private function setEvent(): void {
		$eventId = $this->request->get( 'eventId' );

		if ( !$eventId ) {
			return;
		}

		// Check if the eventId parameter is an integer, which we will assume to be the ID and not title.
		if ( ctype_digit( (string)$eventId ) ) {
			$this->event = $this->eventRepo->findOneBy( [ 'id' => $eventId ] );
		} else {
			// Accept event title for backwards-compatibility.
			$this->event = $this->eventRepo->findOneBy( [
				'program' => $this->program,
				'title' => $eventId,
			] );
		}

		if ( !is_a( $this->event, Event::class ) ) {
			throw new NotFoundHttpException( 'error-not-found' );
		}
	}

	/**
	 * Get the Organizer based on username stored in the session.
	 * @return Organizer
	 */
	protected function getOrganizer(): Organizer {
		return $this->organizerRepo->getOrganizerByUsername(
			$this->requestStack->getSession()->get( 'logged_in_user' )->username
		);
	}

	/**
	 * Is the logged in user an organizer of the given Program? This returns
	 * true for admins, who are defined with the app.admins config parameter.
	 * @param Program $program
	 * @return bool
	 */
	protected function authUserIsOrganizer( Program $program ): bool {
		$username = $this->requestStack->getSession()->get( 'logged_in_user' )->username;

		return in_array( $username, $this->appAdmins ) ||
			in_array( $username, $program->getOrganizerNames() );
	}

	/**
	 * Is the current user an admin?
	 * @return bool
	 */
	protected function userIsAdmin(): bool {
		$username = $this->requestStack->getSession()->get( 'logged_in_user' )->username;
		return in_array( $username, $this->appAdmins );
	}

	/**
	 * Validates that the logged in user is an organizer of the requested Program,
	 * and if not throw an exception (they should never be able to navigate here).
	 * @throws AccessDeniedHttpException
	 */
	private function validateOrganizer(): void {
		if ( isset( $this->program ) && !$this->authUserIsOrganizer( $this->program ) ) {
			throw new AccessDeniedHttpException( 'error-non-organizer' );
		}
	}

	/**
	 * Redirect to homepage if the user is logged out, showing an error message that they need to login.
	 * @throws HttpException
	 */
	private function validateUser(): void {
		if ( $this->requestStack->getSession()->get( 'logged_in_user' ) != '' ) {
			return;
		}
		$this->addFlashMessage( 'danger', 'please-login' );
		throw new HttpException(
			Response::HTTP_TEMPORARY_REDIRECT,
			null,
			null,
			[ 'Location' => $this->router->generate( 'homepage' ) ]
		);
	}
}
