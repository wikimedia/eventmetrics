<?php
/**
 * This file contains the DatabaseAwareWebTestCase class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * This ensures fixtures are loaded with every functional test.
 */
abstract class DatabaseAwareWebTestCase extends EventMetricsTestCase
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

    /** @var Client The Symfony client. */
    protected $client;

    /** @var SessionInterface The session. */
    protected $session;

    /** @var bool Whether to hide error output from the response. */
    protected $suppressErrors = false;

    /**
     * The web crawler used for browsing and capturing elements on the page.
     * @var \Symfony\Component\DomCrawler\Crawler
     */
    protected $crawler;

    /**
     * Whenever we are testing the response in a functional test, we set it
     * on this class property. That way $this->tearDown() can print the stacktrace.
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    /**
     * Runs before each test.
     */
    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->client = static::createClient();
        $this->session = static::$container->get('session');
    }

    public function suppressErrors(): void
    {
        $this->suppressErrors = true;
    }

    /**
     * Add the given username into the session, or default to MusikAnimal
     * who is an organizer of programs created via the fixtures.
     * @param string $username
     */
    public function loginUser(string $username = 'MusikAnimal'): void
    {
        // Create identity mock of user and put it in the session.
        $identityMock = (object)['username' => $username];
        $this->session->set('logged_in_user', $identityMock);
    }

    /**
     * Invalidate the session, logging out the user.
     */
    public function logoutUser(): void
    {
        $this->session->invalidate();
    }

    /**
     * Every functional test sets the class property $this->response, and
     * here after every test finishes we check to see if it was successful.
     * If not, we print the stacktrace produced in the browser, since this
     * would otherwise just return a unhelpful plain 500 error.
     */
    public function tearDown(): void
    {
        // Must be called before parent::tearDown() to have access to the container.
        $this->killDbConnections();

        parent::tearDown();

        if (isset($this->response) && !$this->response->isSuccessful() && false === $this->suppressErrors) {
            $stacktrace = $this->crawler->filter('.stacktrace');
            if ($stacktrace->count()) {
                echo "\n\n".$stacktrace->text();
            }
        }

        // Avoid memory leaks.

        if (isset($this->entityManager)) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
        gc_collect_cycles();

        // Remove properties defined during the test.
        $refl = new \ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }

    /**
     * Kill active database connections, which can persist between tests and consequently exceed the connection limit.
     */
    protected function killDbConnections(): void
    {
        if (!self::$container) {
            return;
        }

        for ($slice = 1; $slice < 9; $slice++) {
            self::$container->get('doctrine')
                ->getConnection('toolforge_s'.$slice)
                ->close();
        }
        self::$container->get('doctrine')
            ->getManager('centralauth')
            ->getConnection()
            ->close();
        self::$container->get('doctrine')
            ->getManager('meta')
            ->getConnection()
            ->close();
    }

    /**
     * Adds a new fixture to be loaded.
     *
     * @param FixtureInterface $fixture
     */
    protected function addFixture(FixtureInterface $fixture): void
    {
        $this->getFixtureLoader()->addFixture($fixture);
    }

    /**
     * Executes all the fixtures that have been loaded so far.
     */
    protected function executeFixtures(): void
    {
        $this->getFixtureExecutor()->execute($this->getFixtureLoader()->getFixtures());
    }

    /**
     * @return ORMExecutor
     */
    private function getFixtureExecutor(): ORMExecutor
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
    private function getFixtureLoader(): ContainerAwareLoader
    {
        if (!$this->fixtureLoader) {
            $this->fixtureLoader = new ContainerAwareLoader(self::$kernel->getContainer());
        }
        return $this->fixtureLoader;
    }

    /**
     * Check that each given route returns a the given response code.
     * @param string[] $routes
     * @param int $expectedResponse
     */
    public function assertRoutesResponses(array $routes, int $expectedResponse): void
    {
        foreach ($routes as $route) {
            $this->client->request('GET', $route);
            $actualResponse = $this->client->getResponse()->getStatusCode();
            static::assertEquals(
                $actualResponse,
                $expectedResponse,
                "Failed: $route expected $expectedResponse response but got $actualResponse"
            );
        }
    }
}
