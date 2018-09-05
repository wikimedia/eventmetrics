<?php
/**
 * This file contains only the DefaultController class.
 */

namespace AppBundle\Controller;

use AppBundle\Model\EventWiki;
use AppBundle\Repository\EventWikiRepository;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use MediaWiki\OAuthClient\Client as OAuthClient;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Exception;
use GuzzleHttp\Client as GuzzleClient;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class DefaultController extends Controller
{
    /** @var OAuthClient The Oauth HTTP client. */
    protected $oauthClient;

    /**
     * Display the homepage.
     * @Route("", name="homepageNoSlash")
     * @Route("/", name="homepage")
     * @return Response
     */
    public function indexAction()
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
    public function backgroundImageAction($windowSize = null)
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

    /**
     * Redirect to Meta for Oauth authentication.
     * @Route("/login", name="login")
     * @return RedirectResponse
     * @throws Exception If initialization fails.
     * @codeCoverageIgnore
     */
    public function loginAction()
    {
        $mockUser = $this->container->getParameter('app.logged_in_user');

        if (isset($mockUser)) {
            $this->get('session')->set('logged_in_user', (object) [
                'username' => $mockUser
            ]);
            return new RedirectResponse('/programs');
        }

        try {
            list($next, $token) = $this->getOauthClient()->initiate();
        } catch (Exception $oauthException) {
            throw $oauthException;
        }

        // Save the request token to the session.
        /** @var Session $session */
        $session = $this->get('session');
        $session->set('oauth.request_token', $token);

        return new RedirectResponse($next);
    }

    /**
     * Receive authentication credentials back from the OAuth wiki.
     * @Route("/oauth_callback", name="OAuthCallback")
     * @param Request $request The HTTP request.
     * @return RedirectResponse
     */
    public function oauthCallbackAction(Request $request)
    {
        // Give up if the required GET params don't exist.
        if (!$request->get('oauth_verifier')) {
            throw $this->createNotFoundException('No OAuth verifier given.');
        }

        // From here we don't test because we'd have to
        // have a fresh OAuth token from MediaWiki.
        // @codeCoverageIgnoreStart

        /** @var Session $session */
        $session = $this->get('session');

        // Complete authentication.
        $client = $this->getOauthClient();
        $token = $session->get('oauth.request_token');
        $verifier = $request->get('oauth_verifier');
        $accessToken = $client->complete($token, $verifier);

        // Store access token, and remove request token.
        $session->set('oauth.access_token', $accessToken);
        $session->remove('oauth.request_token');

        // Store user identity.
        $ident = $client->identify($accessToken);
        $session->set('logged_in_user', $ident);

        // Send to 'My programs' page.
        return $this->redirectToRoute('Programs');

        // @codeCoverageIgnoreEnd
    }

    /**
     * Get an OAuth client, configured to the default project.
     * (This shouldn't really be in this class, but oh well.)
     * @return OAuthClient
     * @codeCoverageIgnore
     */
    protected function getOauthClient()
    {
        if ($this->oauthClient instanceof OAuthClient) {
            return $this->oauthClient;
        }

        $endpoint = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';

        $conf = new ClientConfig($endpoint);
        $consumerKey = $this->getParameter('oauth.key');
        $consumerSecret = $this->getParameter('oauth.secret');

        $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
        $this->oauthClient = new OAuthClient($conf);

        // Use 'oob' as the callback is hardcoded in the consumer registration.
        $this->oauthClient->setCallback('oob');

        return $this->oauthClient;
    }

    /**
     * Log out the user and return to the homepage.
     * @Route("/logout", name="logout")
     * @return RedirectResponse
     */
    public function logoutAction()
    {
        $this->get('session')->invalidate();
        return $this->redirectToRoute('homepage');
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
    public function wikisApiAction(EventWikiRepository $ewRepo)
    {
        return $this->json($ewRepo->getAvailableWikis());
    }
}
