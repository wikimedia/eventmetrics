<?php
/**
 * This file contains the DatabaseAwareWebTestCase class.
 */

namespace Tests\AppBundle\Controller;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * This ensures fixtures are loaded with every functional test.
 */
abstract class DatabaseAwareWebTestCase extends WebTestCase
{
    /**
     * @var ORMExecutor
     */
    private $fixtureExecutor;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var ContainerAwareLoader
     */
    private $fixtureLoader;

    /** @var Container The Symfony container. */
    protected $container;

    /** @var Client The Symfony client. */
    protected $client;

    /** @var SessionInterface The session. */
    protected $session;

    /** @var bool Whether to hide error output from the response. */
    protected $suppressErrors = false;

    /**
     * The web crawler used for browsing and capturing elements on the page.
     * @var Symfony\Component\DomCrawler\Crawler
     */
    protected $crawler;

    /**
     * Whenever we are testing the response in a functional test, we set it
     * on this class property. That way $this->tearDown() can print the stacktrace.
     * @var Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    /**
     * Runs before each test.
     * @param bool $suppressErrors Whether to hide error output from the response.
     */
    public function setUp()
    {
        self::bootKernel();

        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $this->session = $this->container->get('session');
    }

    public function suppressErrors()
    {
        $this->suppressErrors = true;
    }

    /**
     * Add the given username into the session, or default to MusikAnimal
     * who is an organizer of programs created via the fixtures.
     * @param string $username
     */
    public function loginUser($username = 'MusikAnimal')
    {
        // Create identity mock of user and put it in the session.
        $identityMock = (object)['username' => $username];
        $this->session->set('logged_in_user', $identityMock);
    }

    /**
     * Invalidate the session, logging out the user.
     */
    public function logoutUser()
    {
        $this->session->invalidate();
    }

    public function getLoggedInUser()
    {
        return $this->session->get('logged_in_user');
    }

    /**
     * Every functional test sets the class property $this->response, and
     * here after every test finishes we check to see if it was successful.
     * If not, we print the stacktrace produced in the browser, since this
     * would otherwise just return a unhelpful plain 500 error.
     */
    public function tearDown()
    {
        if (isset($this->response) && !$this->response->isSuccessful() && $this->suppressErrors === false) {
            $stacktrace = $this->crawler->filter('.stacktrace');
            if ($stacktrace->count()) {
                echo "\n\n".$stacktrace->text();
            }
        }
    }

    /**
     * Adds a new fixture to be loaded.
     *
     * @param FixtureInterface $fixture
     */
    protected function addFixture(FixtureInterface $fixture)
    {
        $this->getFixtureLoader()->addFixture($fixture);
    }

    /**
     * Executes all the fixtures that have been loaded so far.
     */
    protected function executeFixtures()
    {
        $this->getFixtureExecutor()->execute($this->getFixtureLoader()->getFixtures());
    }

    /**
     * @return ORMExecutor
     */
    private function getFixtureExecutor()
    {
        if (!$this->fixtureExecutor) {
            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $this->entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
            $this->fixtureExecutor = new ORMExecutor(
                $this->entityManager,
                new ORMPurger($this->entityManager)
            );
        }
        return $this->fixtureExecutor;
    }

    /**
     * @return ContainerAwareLoader
     */
    private function getFixtureLoader()
    {
        if (!$this->fixtureLoader) {
            $this->fixtureLoader = new ContainerAwareLoader(self::$kernel->getContainer());
        }
        return $this->fixtureLoader;
    }
}
