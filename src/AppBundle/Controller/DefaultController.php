<?php
/**
 * This file contains only the DefaultController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 */
class DefaultController extends Controller
{
    /**
     * Display the homepage.
     * @Route("", name="homepageNoSlash")
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        return $this->render('default/index.html.twig', [
            'backgroundUrl' => $this->getBackgroundImageUrl(),
        ]);
    }

    /**
     * Get the URL of a random background image.
     */
    private function getBackgroundImageUrl()
    {
        /** @var string[] List of titles of files on Commons. */
        $files = $this->container->getParameter('picture_of_the_day');

        /** @var string A random file */
        $file = $files[array_rand($files)];

        /** @var string[] Parameters to be passed to the API. */
        $params = [
            'action' => 'query',
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|canonicaltitle',
            'titles' => $file,
            'format' => 'json',
            'formatversion' => 2,
        ];

        /** @var GuzzleHttp\Client $client */
        $client = $this->get('guzzle.client.commons');

        $res = $client->get('', ['query' => $params])
            ->getBody()
            ->getContents();

        return json_decode($res)->query
            ->pages[0]
            ->imageinfo[0]
            ->url;
    }
}
