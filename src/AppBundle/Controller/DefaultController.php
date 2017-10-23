<?php
/**
 * This file contains only the DefaultController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 */
class DefaultController extends Controller
{
    /**
     * Display the homepage.
     * @Route("/", name="homepage")
     * @param Request $request The HTTP request.
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }
}
