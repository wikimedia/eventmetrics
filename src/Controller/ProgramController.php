<?php declare( strict_types=1 );

namespace App\Controller;

use App\Form\ProgramType;
use App\Model\Event;
use App\Model\Program;
use App\Repository\OrganizerRepository;
use App\Repository\ProgramRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;

/**
 * The ProgramController handles listing, creating and editing programs.
 */
class ProgramController extends EntityController {
	/**
	 * Display a list of the programs.
	 * @Route("/programs", name="Programs")
	 * @param ProgramRepository $programRepo
	 * @param OrganizerRepository $organizerRepo
	 * @return Response
	 */
	public function indexAction( ProgramRepository $programRepo, OrganizerRepository $organizerRepo ): Response {
		$organizer = $this->getOrganizer();

		if ( $this->userIsAdmin() ) {
			$programs = $programRepo->findAll();
		} else {
			$programs = $organizer->getPrograms();
		}

		return $this->render( 'programs/index.html.twig', [
			'programs' => $programs,
			'programRepo' => $programRepo,
			'gmTitle' => 'my-programs',
			'metrics' => $organizerRepo->getUniqueMetrics( $organizer ),
			'visibleMetrics' => Event::getVisibleMetrics(),
		] );
	}

	/**
	 * Show a form to create a new program.
	 * @Route("/programs/new", name="NewProgram")
	 * @return Response
	 */
	public function newAction(): Response {
		$organizer = $this->getOrganizer();
		$program = new Program( $organizer );

		// Handle the Form for the request, and redirect if they submitted.
		$form = $this->handleFormSubmission( $program );
		if ( $form instanceof RedirectResponse ) {
			// Flash message will be shown at the top of the page.
			$this->addFlashMessage( 'success', 'program-created', [ $program->getDisplayTitle() ] );
			return $form;
		}

		return $this->render( 'programs/new.html.twig', [
			'form' => $form->createView(),
			'gmTitle' => 'create-new-program',
		] );
	}

	/**
	 * Show a form to edit the given program.
	 * @Route("/programs/{programId}/edit", name="EditProgram")
	 * @return Response
	 */
	public function editAction(): Response {
		// Handle the Form for the request, and redirect if they submitted.
		$form = $this->handleFormSubmission( $this->program );
		if ( $form instanceof RedirectResponse ) {
			// Flash message will be shown at the top of the page.
			$this->addFlashMessage( 'success', 'program-updated', [ $this->program->getDisplayTitle() ] );
			return $form;
		}

		return $this->render( 'programs/edit.html.twig', [
			'form' => $form->createView(),
			'program' => $this->program,
			'gmTitle' => $this->program->getDisplayTitle(),
		] );
	}

	/**
	 * Delete a program.
	 * @Route("/programs/{programId}/delete", name="DeleteProgram")
	 * @return RedirectResponse
	 */
	public function deleteAction(): RedirectResponse {
		// Flash message will be shown at the top of the page.
		$this->addFlashMessage( 'danger', 'program-deleted', [ $this->program->getDisplayTitle() ] );

		$this->em->remove( $this->program );
		$this->em->flush();

		return $this->redirectToRoute( 'Programs' );
	}

	/**
	 * Show a specific program, listing all of its events.
	 * @Route("/programs/{programId}", name="Program")
	 * @param ProgramRepository $programRepo
	 * @return Response
	 */
	public function showAction( ProgramRepository $programRepo ): Response {
		return $this->render( 'programs/show.html.twig', [
			'program' => $this->program,
			'metrics' => $programRepo->getUniqueMetrics( $this->program ),
			'isOrganizer' => $this->authUserIsOrganizer( $this->program ),
			'visibleMetrics' => Event::getVisibleMetrics(),
		] );
	}

	/**
	 * Handle creation or updating of a Program on form submission.
	 * @param Program $program
	 * @return FormInterface|RedirectResponse
	 */
	private function handleFormSubmission( Program $program ): RedirectResponse|FormInterface {
		$form = $this->createForm( ProgramType::class, $program );
		$form->handleRequest( $this->request );

		if ( $form->isSubmitted() && $form->isValid() ) {
			$program = $form->getData();
			$this->em->persist( $program );
			$this->em->flush();

			return $this->redirectToRoute( 'Program', [ 'programId' => $program->getId() ] );
		}

		return $form;
	}
}
