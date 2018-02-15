<?php
/**
 * This file contains the abstract EntityController.
 */

namespace AppBundle\Controller;

use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The EntityController sets class-level properties and
 * provides methods shared amongst the ProgramController,
 * EventController, and EventDataController.
 * @abstract
 */
abstract class EntityController extends Controller
{
    /** @var EntityManager The Doctrine entity manager. */
    protected $em;

    /**
     * Constructor for the abstract EntityController.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->em = $container->get('doctrine')->getManager();
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
        $username = $this->get('session')->get('logged_in_user')->username;

        return in_array($username, $this->container->getParameter('app.admins')) ||
            in_array($username, $program->getOrganizerNames());
    }

    /**
     * Validates that the logged in user is an organizer of the given Program,
     * and if not throw an exception (they should never be able to navigate here).
     * @param  Program $program
     * @throws AccessDeniedHttpException
     */
    protected function validateOrganizer(Program $program)
    {
        if (!$this->authUserIsOrganizer($program)) {
            throw new AccessDeniedHttpException(
                'You are not an organizer of this program.'
            );
        }
    }
}
