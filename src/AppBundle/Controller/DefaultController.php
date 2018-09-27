<?php
/**
 * This file contains only the DefaultController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Repository\EventWikiRepository;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class DefaultController extends Controller
{

    /**
     * Display the homepage.
     * @Route("", name="homepageNoSlash")
     * @Route("/", name="homepage")
     * @Route("/", name="home")
     * @return Response
     */
    public function indexAction(): Response
    {
        if ($this->container->get('session')->get('logged_in_user')) {
            return $this->redirectToRoute('Programs');
        }
        return $this->render('default/index.html.twig');
    }

    /**
     * Get the URL of a random background image.
     * @Route("/api/background/{windowSize}", name="BackgroundImage")
     * @Route("/api/background/{windowSize}/", name="BackgroundImageSlash")
     * @param int $windowSize Device's screen size, so that we don't
     *   download imagery larger than what's necessary.
     * @return JsonResponse
     * This requires access to the API, and while we have a MediaWiki install
     * with the continuous integration build, we don't want to bother with
     * uploading images to test this just-for-fun method.
     * @codeCoverageIgnore
     */
    public function backgroundImageAction(?int $windowSize = null): JsonResponse
    {
        /** @var string[] $files List of titles of files on Commons. */
        $files = $this->container->getParameter('picture_of_the_day');

        /** @var string $file A random file */
        $file = $files[array_rand($files)];

        /** @var string[] $params Parameters to be passed to the API. */
        $params = [
            'action' => 'query',
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|canonicaltitle',
            'iiurlwidth' => 300,
            'titles' => $file,
            'format' => 'json',
            'formatversion' => 2,
        ];

        if (isset($windowSize)) {
            $params['iiurlwidth'] = $windowSize;
        }

        /** @var GuzzleClient $client */
        $client = $this->get('eight_points_guzzle.client.commons');

        $res = $client->get('', ['query' => $params])
            ->getBody()
            ->getContents();

        $imageinfo = (array)json_decode($res)->query
            ->pages[0]
            ->imageinfo[0];

        return new JsonResponse($imageinfo);
    }

    /*****************
     * API ENDPOINTS *
     *****************/

    /**
     * Get all wikis available on the replicas. This is used by the frontend for autocompletion when entering wikis.
     * We do not use the Sitematrix API because (a) it's format is hard to parse, and moreover (b) newly introduced
     * wikis may be in production but not on the replicas, which will cause Grant Metrics to error out.
     * @Route("/api/wikis", name="WikisApi")
     * @param EventWikiRepository $ewRepo
     * @return JsonResponse
     */
    public function wikisApiAction(EventWikiRepository $ewRepo): JsonResponse
    {
        return $this->json($ewRepo->getAvailableWikis());
    }
}
