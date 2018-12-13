<?php

declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Organizer;
use AppBundle\Model\Program;
use AppBundle\Repository\PageviewsRepository;
use Tests\AppBundle\EventMetricsTestCase;

class PageviewsRepositoryTest extends EventMetricsTestCase
{

    /** @var PageviewsRepository */
    protected $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = new PageviewsRepository();
    }

    /**
     * @covers \AppBundle\Repository\PageviewsRepository::getPerArticle()
     */
    public function testPerArticle():void
    {
        $program = new Program(new Organizer('Example user'));
        $eventWiki = new EventWiki(new Event($program, 'Test'), 'zh.wikipedia');
        $pageviews = $this->repo->getPerArticle(
            $eventWiki,
            '一期一會',
            PageviewsRepository::GRANULARITY_DAILY,
            new \DateTime('2019-01-01'),
            new \DateTime('2019-01-02')
        );
        static::assertEquals(['items' => [[
            'project' => 'zh.wikipedia',
            'article' => '一期一會',
            'granularity' => 'daily',
            'timestamp' => '2019010100',
            'access' => 'all-access',
            'agent' => 'user',
            'views' => 281,
        ], [
            'project' => 'zh.wikipedia',
            'article' => '一期一會',
            'granularity' => 'daily',
            'timestamp' => '2019010200',
            'access' => 'all-access',
            'agent' => 'user',
            'views' => 255,
        ]]], $pageviews);
    }
}
