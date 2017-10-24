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
     */
    public function indexAction()
    {
        return $this->render('default/index.html.twig');
    }
}
