<?php

namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Krinkle\Intuition\Intuition;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as SymfonyController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * This is the parent Controller for all controllers in this application.
 */
abstract class Controller extends SymfonyController
{

    /** @var ContainerInterface Symfony's container interface. */
    protected $container;

    /** @var Request The request object. */
    protected $request;

    /** @var SessionInterface Symfony's session interface. */
    protected $session;

    /** @var EntityManagerInterface The Doctrine entity manager. */
    protected $em;

    /** @var Intuition */
    protected $intuition;

    /**
     * Constructor for the abstract Controller.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param SessionInterface $session
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @param Intuition $intuition
     */
    public function __construct(
        RequestStack $requestStack,
        ContainerInterface $container,
        SessionInterface $session,
        EntityManagerInterface $em,
        Intuition $intuition
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->container = $container;
        $this->em = $em;
        $this->session = $session;
        $this->intuition = $intuition;
    }

    /**
     * Add a flash message.
     * @param string $type
     * @param string $messageName
     * @param array $vars
     */
    public function addFlashMessage(string $type, string $messageName, array $vars = [])
    {
        $options = [
            'domain' => 'grantmetrics',
            'variables' => $vars,
        ];
        $message = $this->intuition->msg($messageName, $options);
        $this->addFlash($type, $message);
    }
}
