<?php
/**
 * This file contains only the LoadFixtures class.
 */

declare(strict_types=1);

namespace AppBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Nelmio\Alice\Loader\NativeLoader;

/**
 * The LoadFixtures class loads fixtures into the ObjectManager.
 */
class LoadFixtures implements FixtureInterface
{
    /** @var string Feature set to load, either 'basic' or 'extended'. */
    protected $set;

    /**
     * Constructor for LoadFixtures.
     * @param string $set Which fixture set to load based on what tests we're running. Either 'basic' or 'extended'.
     */
    public function __construct(string $set = 'basic')
    {
        $this->set = $set;
    }

    /**
     * Load the fixtures into the ObjectManager.
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager): void
    {
        $loader = new NativeLoader();
        $objectSet = $loader->loadFile(__DIR__.'/'.$this->set.'.yml');
        foreach ($objectSet->getObjects() as $object) {
            $manager->persist($object);
        }
        $manager->flush();
    }
}
