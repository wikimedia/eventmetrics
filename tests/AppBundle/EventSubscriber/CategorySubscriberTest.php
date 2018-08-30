<?php
/**
 * This file contains only the CategorySubscriberTest class.
 */

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\DataFixtures\ORM\LoadFixtures;
use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;
use Tests\AppBundle\Controller\DatabaseAwareWebTestCase;

/**
 * Class CategorySubscriberTest
 */
class CategorySubscriberTest extends DatabaseAwareWebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->addFixture(new LoadFixtures('extended'));
        $this->executeFixtures();
    }

    /**
     * Persisting categories to the grantmetrics database.
     * @covers \AppBundle\EventSubscriber\CategorySubscriber::prePersist()
     */
    public function testPrePersist()
    {
        /** @var Event $event */
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        /** @var EventWiki $eventWiki */
        $eventWiki = $event->getWikiByDomain('en.wikipedia');

        $category = new EventCategory($eventWiki);
        $category->setTitle('  Living_people  ');

        $this->entityManager->persist($event);

        // 173 = Living_people
        static::assertEquals(173, $category->getCategoryId());

        // Flush so that self::postLoadSpec() will have a category in the database to fetch.
        $this->entityManager->flush();
        $this->entityManager->clear(); // Forces future findBy's to fetch from database instead of cache.
        $this->postLoadSpec();
    }

    /**
     * Loading categories from the grantmetrics database. This is not a separate test case because it uses the
     * EventCategory created by self::testPrePersist(), which would otherwise get erased.
     * @covers \AppBundle\EventSubscriber\CategorySubscriber::postLoad()
     */
    public function postLoadSpec()
    {
        /** @var Event $event */
        $event = $this->entityManager
            ->getRepository('Model:Event')
            ->findOneBy(['title' => 'Oliver_and_Company']);

        // For good measure.
        static::assertEquals(1, $event->getNumCategories());

        /** @var EventCategory $category */
        $category = $event->getCategories()->first();

        static::assertEquals('Living people', $category->getTitle());
    }
}
