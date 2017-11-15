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

    public function setUp()
    {
        self::bootKernel();
    }

    /**
     * Every functional test sets the class property $this->response, and
     * here after every test finishes we check to see if it was successful.
     * If not, we print the stacktrace produced in the browser, since this
     * would otherwise just return a unhelpful plain 500 error.
     */
    public function tearDown()
    {
        if (isset($this->response) && !$this->response->isSuccessful()) {
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
