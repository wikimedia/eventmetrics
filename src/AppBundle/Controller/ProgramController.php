<?php
/**
 * This file contains only the ProgramController class.
 */

namespace AppBundle\Controller;

use AppBundle\Form\ProgramType;
use AppBundle\Model\Program;
use AppBundle\Repository\OrganizerRepository;
use AppBundle\Repository\ProgramRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The ProgramController handles listing, creating and editing programs.
 */
class ProgramController extends EntityController
{
    /**
     * Display a list of the programs.
     * @Route("/programs", name="Programs")
     * @Route("/programs/", name="ProgramsSlash")
     * @param ProgramRepository $programRepo
     * @param OrganizerRepository $organizerRepo
     * @return Response
     */
    public function indexAction(ProgramRepository $programRepo, OrganizerRepository $organizerRepo)
    {
        $organizer = $this->getOrganizer();

        if ($this->userIsAdmin()) {
            $programs = $programRepo->findAll();
        } else {
            $programs = $organizer->getPrograms();
        }

        return $this->render('programs/index.html.twig', [
            'programs' => $programs,
            'programRepo' => $programRepo,
            'gmTitle' => 'my-programs',
            'metrics' => $organizerRepo->getUniqueMetrics($organizer),
        ]);
    }

    /**
     * Show a form to create a new program.
     * @Route("/programs/new", name="NewProgram")
     * @Route("/programs/new/", name="NewProgramSlash")
     * @return Response|RedirectResponse
     */
    public function newAction()
    {
        $organizer = $this->getOrganizer();
        $program = new Program($organizer);

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($program);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlashMessage('success', 'program-created', [$program->getDisplayTitle()]);
            return $form;
        }

        return $this->render('programs/new.html.twig', [
            'form' => $form->createView(),
            'gmTitle' => 'create-new-program',
        ]);
    }

    /**
     * Show a form to edit the given program.
     * @Route("/programs/edit/{programTitle}", name="EditProgram")
     * @Route("/programs/edit/{programTitle}/", name="EditProgramSlash")
     * @return Response|RedirectResponse
     */
    public function editAction()
    {
        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($this->program);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlashMessage('success', 'program-updated', [$this->program->getDisplayTitle()]);
            return $form;
        }

        return $this->render('programs/edit.html.twig', [
            'form' => $form->createView(),
            'program' => $this->program,
            'gmTitle' => $this->program->getDisplayTitle(),
        ]);
    }

    /**
     * Delete a program.
     * @Route("/programs/delete/{programTitle}", name="DeleteProgram")
     * @Route("/programs/delete/{programTitle}/", name="DeleteProgramSlash")
     * @return RedirectResponse
     */
    public function deleteAction()
    {
        // Flash message will be shown at the top of the page.
        $this->addFlashMessage('danger', 'program-deleted', [$this->program->getDisplayTitle()]);

        $this->em->remove($this->program);
        $this->em->flush();

        return $this->redirectToRoute('Programs');
    }

    /**
     * Show a specific program, listing all of its events.
     * @Route("/programs/{programTitle}", name="Program")
     * @Route("/programs/{programTitle}/", name="ProgramSlash")
     * @param ProgramRepository $programRepo
     * @return Response
     */
    public function showAction(ProgramRepository $programRepo)
    {
        return $this->render('programs/show.html.twig', [
            'program' => $this->program,
            'metrics' => $programRepo->getUniqueMetrics($this->program),
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
        ]);
    }

    /**
     * Handle creation or updating of a Program on form submission.
     * @param Program $program
     * @return FormInterface|RedirectResponse
     */
    private function handleFormSubmission(Program $program)
    {
        $form = $this->createForm(ProgramType::class, $program);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            $program = $form->getData();
            $this->em->persist($program);
            $this->em->flush();

            return $this->redirectToRoute('Programs');
        }

        return $form;
    }
}
