<?php
/**
 * This file contains only the LoadFixtures class.
 */

namespace AppBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Nelmio\Alice\Fixtures;

/**
 * The LoadFixtures class loads fixtures into the ObjectManager.
 */
class LoadFixtures implements FixtureInterface
{
    /**
     * Load the fixtures into the ObjectManager.
     * @param  ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        Fixtures::load(__DIR__.'/fixtures.yml', $manager);
    }
}
