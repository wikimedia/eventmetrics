<?php
/**
 * This file contains the abstract EntityController.
 */

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The EntityController sets class-level properties and
 * provides methods shared amongst the ProgramController,
 * EventController, and EventDataController.
 * @abstract
 */
abstract class EntityController extends Controller
{
    /** @var ContainerInterface Symfony's container interface. */
    protected $container;

    /** @var Request The request object. */
    protected $request;

    /** @var SessionInterface Symfony's session interface. */
    protected $session;

    /** @var EntityManagerInterface The Doctrine entity manager. */
    protected $em;

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
     */
    public function __construct(
        RequestStack $requestStack,
        ContainerInterface $container,
        SessionInterface $session,
        EntityManagerInterface $em
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->container = $container;
        $this->em = $em;
        $this->session = $session;

        $this->validateUser();
        $this->setProgramAndEvent();
        $this->validateOrganizer();
    }

    /**
     * Check the request and if there are parameters for eventTitle or programTitle,
     * find and set class properties for the corresponding entity.
     */
    private function setProgramAndEvent()
    {
        $this->setProgram();
        $this->setEvent();
    }

    /**
     * Check the request and if the programTitle is set, find and set
     * $this->program with the corresponding entity.
     */
    private function setProgram()
    {
        if ($programTitle = $this->request->get('programTitle')) {
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
     */
    private function setEvent()
    {
        if ($eventTitle = $this->request->get('eventTitle')) {
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
    protected function getOrganizer()
    {
        $organizerRepo = $this->em->getRepository(Organizer::class);
        $organizerRepo->setContainer($this->container);
        return $organizerRepo->getOrganizerByUsername(
            $this->get('session')->get('logged_in_user')->username
        );
    }

    /**
     * Is the logged in user an organizer of the given Program? This returns
     * true for admins, who are defined with the app.admins config parameter.
     * @param  Program $program
     * @return bool
     */
    protected function authUserIsOrganizer(Program $program)
    {
        $username = $this->session->get('logged_in_user')->username;

        return in_array($username, $this->container->getParameter('app.admins')) ||
            in_array($username, $program->getOrganizerNames());
    }

    /**
     * Validates that the logged in user is an organizer of the requested Program,
     * and if not throw an exception (they should never be able to navigate here).
     * @throws AccessDeniedHttpException
     */
    private function validateOrganizer()
    {
        if (isset($this->program) && !$this->authUserIsOrganizer($this->program)) {
            throw new AccessDeniedHttpException('error-non-organizer');
        }
    }

    /**
     * Redirect to /login if the user is logged out.
     * @throws HttpException
     */
    private function validateUser()
    {
        if ($this->session->get('logged_in_user') != '') {
            return;
        }

        $rootPath = $this->container->getParameter('app.root_path');

        throw new HttpException(
            Response::HTTP_TEMPORARY_REDIRECT,
            null,
            null,
            ['Location' => "$rootPath/login"]
        );
    }
}
