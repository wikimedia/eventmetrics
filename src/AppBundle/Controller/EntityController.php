<?php
/**
 * This file contains the abstract EntityController.
 */

namespace AppBundle\Controller;

use AppBundle\Model\Organizer;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
}
