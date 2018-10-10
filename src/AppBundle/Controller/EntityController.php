<?php
/**
 * This file contains the abstract EntityController.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use AppBundle\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Krinkle\Intuition\Intuition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The EntityController sets class-level properties and
 * provides methods shared amongst the ProgramController,
 * EventController, and EventDataController.
 * @abstract
 */
abstract class EntityController extends Controller
{

    /** @var ValidatorInterface Used when manually validating Models, as opposed to using Symfony Forms. */
    protected $validator;

    /** @var Program The Program being requested. */
    protected $program;

    /** @var Event The Event being requested. */
    protected $event;

    /**
     * Constructor for the abstract EntityController.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param SessionInterface $session
     * @param EntityManagerInterface $em
     * @param Intuition $intuition
     */
    public function __construct(
        RequestStack $requestStack,
        ContainerInterface $container,
        SessionInterface $session,
        EntityManagerInterface $em,
        Intuition $intuition
    ) {
        parent::__construct($requestStack, $container, $session, $em, $intuition);
        $this->validateUser();
        $this->setProgramAndEvent();
        $this->validateOrganizer();
    }

    /**
     * Service injection point, configured in services.yml
     * @param ValidatorInterface $validator
     * @codeCoverageIgnore
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * Check the request and if there are parameters for eventTitle or programTitle,
     * find and set class properties for the corresponding entity.
     */
    private function setProgramAndEvent(): void
    {
        $this->setProgram();
        $this->setEvent();
    }

    /**
     * Check the request and if the programTitle is set, find and set
     * $this->program with the corresponding entity.
     * @throws NotFoundHttpException
     */
    private function setProgram(): void
    {
        $programTitle = $this->request->get('programTitle');
        if ($programTitle) {
            $this->program = $this->em->getRepository(Program::class)
                ->findOneBy(['title' => $programTitle]);

            if (!is_a($this->program, Program::class)) {
                throw new NotFoundHttpException('error-not-found');
            }
        }
    }

    /**
     * Check the request and if the eventTitle is set, find and set
     * $this->event with the corresponding entity.
     * @throws NotFoundHttpException
     */
    private function setEvent(): void
    {
        $eventTitle = $this->request->get('eventTitle');
        if ($eventTitle) {
            $this->event = $this->em->getRepository(Event::class)
                ->findOneBy([
                    'program' => $this->program,
                    'title' => $eventTitle,
                ]);

            if (!is_a($this->event, Event::class)) {
                throw new NotFoundHttpException('error-not-found');
            }
        }
    }

    /**
     * Get the Organizer based on username stored in the session.
     * @return Organizer
     */
    protected function getOrganizer(): Organizer
    {
        /** @var OrganizerRepository $organizerRepo */
        $organizerRepo = $this->em->getRepository(Organizer::class);

        $organizerRepo->setContainer($this->container);
        return $organizerRepo->getOrganizerByUsername(
            $this->get('session')->get('logged_in_user')->username
        );
    }

    /**
     * Is the logged in user an organizer of the given Program? This returns
     * true for admins, who are defined with the app.admins config parameter.
     * @param Program $program
     * @return bool
     */
    protected function authUserIsOrganizer(Program $program): bool
    {
        $username = $this->session->get('logged_in_user')->username;

        return in_array($username, $this->container->getParameter('app.admins')) ||
            in_array($username, $program->getOrganizerNames());
    }

    /**
     * Is the current user an admin?
     * @return bool
     */
    protected function userIsAdmin(): bool
    {
        $username = $this->session->get('logged_in_user')->username;
        return in_array($username, $this->container->getParameter('app.admins'));
    }

    /**
     * Validates that the logged in user is an organizer of the requested Program,
     * and if not throw an exception (they should never be able to navigate here).
     * @throws AccessDeniedHttpException
     */
    private function validateOrganizer(): void
    {
        if (isset($this->program) && !$this->authUserIsOrganizer($this->program)) {
            throw new AccessDeniedHttpException('error-non-organizer');
        }
    }

    /**
     * Redirect to /login if the user is logged out.
     * @throws HttpException
     */
    private function validateUser(): void
    {
        if ('' != $this->session->get('logged_in_user')) {
            return;
        }
        $this->addFlashMessage('danger', 'please-login');
        $rootPath = $this->container->getParameter('app.root_path');
        throw new HttpException(
            Response::HTTP_TEMPORARY_REDIRECT,
            null,
            null,
            ['Location' => $rootPath]
        );
    }
}
