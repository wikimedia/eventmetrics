<?php declare( strict_types=1 );

namespace App\Tests\Controller;

use App\Tests\EventMetricsTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use ReflectionObject;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * This ensures fixtures are loaded with every functional test.
 */
abstract class DatabaseAwareWebTestCase extends EventMetricsTestCase {
	/** @var ORMExecutor */
	private ORMExecutor $fixtureExecutor;

	/**
	 * @var EntityManager|null
	 */
	protected ?EntityManager $entityManager;

	/** @var ContainerAwareLoader */
	private ContainerAwareLoader $fixtureLoader;

	/** @var KernelBrowser|null The Symfony client. */
	protected ?KernelBrowser $client;

	/** @var SessionInterface|null The session. */
	protected ?SessionInterface $session;

	/** @var bool|null Whether to hide error output from the response. */
	protected ?bool $suppressErrors = false;

	/**
	 * The web crawler used for browsing and capturing elements on the page.
	 * @var Crawler|null
	 */
	protected ?Crawler $crawler;

	/**
	 * Whenever we are testing the response in a functional test, we set it
	 * on this class property. That way $this->tearDown() can print the stacktrace.
	 * @var Response|null
	 */
	protected ?Response $response;

	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->client = static::createClient();
		$this->session = static::getContainer()->get( 'session' );
	}

	/**
	 * Suppress the stacktrace output in the browser.
	 */
	public function suppressErrors(): void {
		$this->suppressErrors = true;
	}

	/**
	 * Add the given username into the session, or default to MusikAnimal
	 * who is an organizer of programs created via the fixtures.
	 * @param string $username
	 */
	public function loginUser( string $username = 'MusikAnimal' ): void {
		// Create identity mock of user and put it in the session.
		$identityMock = (object)[ 'username' => $username ];
		$this->session->set( 'logged_in_user', $identityMock );
	}

	/**
	 * Invalidate the session, logging out the user.
	 */
	public function logoutUser(): void {
		$this->session->remove( 'logged_in_user' );
		$this->session->invalidate();
	}

	/**
	 * Every functional test sets the class property $this->response, and
	 * here after every test finishes we check to see if it was successful.
	 * If not, we print the stacktrace produced in the browser, since this
	 * would otherwise just return a unhelpful plain 500 error.
	 */
	public function tearDown(): void {
		// Must be called before parent::tearDown() to have access to the container.
		$this->killDbConnections();

		parent::tearDown();

		if ( isset( $this->response ) && !$this->response->isSuccessful() && $this->suppressErrors === false ) {
			$stacktrace = $this->crawler->filter( '.stacktrace' );
			if ( $stacktrace->count() ) {
				echo "\n\n" . $stacktrace->text();
			}
		}

		// Avoid memory leaks.

		if ( isset( $this->entityManager ) ) {
			$this->entityManager->close();
			$this->entityManager = null;
		}
		gc_collect_cycles();

		// Remove properties defined during the test.
		$refl = new ReflectionObject( $this );
		foreach ( $refl->getProperties() as $prop ) {
			if ( !$prop->isStatic() && !str_starts_with( $prop->getDeclaringClass()->getName(), 'PHPUnit_' ) ) {
				$prop->setAccessible( true );
				$prop->setValue( $this, null );
			}
		}
	}

	/**
	 * Kill active database connections, which can persist between tests and consequently exceed the connection limit.
	 */
	protected function killDbConnections(): void {
		if ( !static::getContainer() ) {
			return;
		}

		for ( $slice = 1; $slice < 9; $slice++ ) {
			static::getContainer()->get( 'doctrine' )
				->getConnection( 'toolforge_s' . $slice )
				->close();
		}
		static::getContainer()->get( 'doctrine' )
			->getManager( 'centralauth' )
			->getConnection()
			->close();
		static::getContainer()->get( 'doctrine' )
			->getManager( 'meta' )
			->getConnection()
			->close();
	}

	/**
	 * Adds a new fixture to be loaded.
	 *
	 * @param FixtureInterface $fixture
	 */
	protected function addFixture( FixtureInterface $fixture ): void {
		$this->getFixtureLoader()->addFixture( $fixture );
	}

	/**
	 * Executes all the fixtures that have been loaded so far.
	 */
	protected function executeFixtures(): void {
		$this->getFixtureExecutor()->execute( $this->getFixtureLoader()->getFixtures() );
	}

	/**
	 * @return ORMExecutor
	 */
	private function getFixtureExecutor(): ORMExecutor {
		if ( !isset( $this->fixtureExecutor ) ) {
			/** @var EntityManager $entityManager */
			$this->entityManager = static::getContainer()->get( 'doctrine' )->getManager();
			$this->fixtureExecutor = new ORMExecutor(
				$this->entityManager,
				new ORMPurger( $this->entityManager )
			);
		}
		return $this->fixtureExecutor;
	}

	/**
	 * @return ContainerAwareLoader
	 */
	private function getFixtureLoader(): ContainerAwareLoader {
		if ( !isset( $this->fixtureLoader ) ) {
			$this->fixtureLoader = new ContainerAwareLoader( static::getContainer() );
		}
		return $this->fixtureLoader;
	}

	/**
	 * Check that each given route returns a the given response code.
	 * @param string[] $routes
	 * @param int $expectedResponse
	 */
	public function assertRoutesResponses( array $routes, int $expectedResponse ): void {
		foreach ( $routes as $route ) {
			$this->client->request( 'GET', $route );
			$actualResponse = $this->client->getResponse()->getStatusCode();
			static::assertEquals(
				$actualResponse,
				$expectedResponse,
				"Failed: $route expected $expectedResponse response but got $actualResponse"
			);
		}
	}
}
