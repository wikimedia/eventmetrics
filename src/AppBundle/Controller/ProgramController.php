<?php
/**
 * This file contains only the ProgramController class.
 */

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\Program;
use AppBundle\Model\Organizer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Form;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * The ProgramController handles listing, creating and editing programs.
 */
class ProgramController extends EntityController
{
    /**
     * Display a list of the programs.
     * @Route("/programs", name="Programs")
     * @Route("/programs/", name="ProgramsSlash")
     * @return Response
     */
    public function indexAction()
    {
        // FIXME: workaround to avoid calling the UserSubscriber
        //   when Participant objects are loaded.
        $programRepo = $this->em->getRepository(Program::class);
        $programRepo->setContainer($this->container);

        $organizer = $this->getOrganizer();
        $organizerRepo = $this->em->getRepository(Organizer::class);
        $organizerRepo->setContainer($this->container);

        if ($this->userIsAdmin()) {
            $programs = $programRepo->findAll();
        } else {
            $programs = $organizer->getPrograms();
        }

        return $this->render('programs/index.html.twig', [
            'programs' => $programs,
            'programRepo' => $programRepo,
            'gmTitle' => 'my-programs',
            'retentionThreshold' => Event::getAvailableMetrics()['retention'],
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
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
                'program-created',
                $program->getDisplayTitle(),
            ]);
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
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
                'program-updated',
                $this->program->getDisplayTitle(),
            ]);
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
        $this->addFlash('danger', /** @scrutinizer ignore-type */ [
            'program-deleted',
            $this->program->getDisplayTitle(),
        ]);

        $this->em->remove($this->program);
        $this->em->flush();

        return $this->redirectToRoute('Programs');
    }

    /**
     * Show a specific program, listing all of its events.
     * @Route("/programs/{programTitle}", name="Program")
     * @Route("/programs/{programTitle}/", name="ProgramSlash")
     * @return Response
     */
    public function showAction()
    {
        $programRepo = $this->em->getRepository(Program::class);
        $programRepo->setContainer($this->container);

        return $this->render('programs/show.html.twig', [
            'program' => $this->program,
            'retentionThreshold' => Event::getAvailableMetrics()['retention'],
            'metrics' => $programRepo->getUniqueMetrics($this->program),
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
        ]);
    }

    /**
     * Handle creation or updating of a Program on form submission.
     * @param  Program $program
     * @return Form|RedirectResponse
     */
    private function handleFormSubmission(Program $program)
    {
        $form = $this->getFormForProgram($program);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            $program = $form->getData();
            $this->em->persist($program);
            $this->em->flush();

            return $this->redirectToRoute('Programs');
        }

        return $form;
    }

    /**
     * Build a form for the given program.
     * @param  Program $program
     * @return Form
     */
    private function getFormForProgram(Program $program)
    {
        $builder = $this->createFormBuilder($program)
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ]
            ])
            ->add('organizers', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'constraints' => [new Valid()],
            ])
            ->add('submit', SubmitType::class);

        $builder->get('organizers')
            ->addModelTransformer($this->getCallbackTransformer());

        return $builder->getForm();
    }

    /**
     * Transform organizer data to or from the form.
     * This essentially pulls in the username from the user ID,
     * and sets the user ID before persisting so that the username
     * can be validated.
     * @return CallbackTransformer
     */
    private function getCallbackTransformer()
    {
        return new CallbackTransformer(
            function ($organizerObjects) {
                return array_map(function ($organizer) {
                    return $organizer->getUsername();
                }, $organizerObjects->toArray());
            },
            function ($organizerNames) {
                return array_map(function ($organizerName) {
                    $organizerRepo = $this->em->getRepository(Organizer::class);
                    $organizerRepo->setContainer($this->container);
                    return $organizerRepo->getOrganizerByUsername($organizerName);
                }, $organizerNames);
            }
        );
    }
}
