<?php

declare(strict_types=1);

namespace Tests\AppBundle\Repository;

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
        $pageviews = $this->repo->getPerArticle(
            'zh.wikipedia',
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
