<?php

declare(strict_types=1);

namespace Tests\AppBundle\Repository;

use AppBundle\Repository\PageviewsRepository;
use DateTime;
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
     * @covers \AppBundle\Repository\PageviewsRepository::getPageviews()
     */
    public function testPageviews(): void
    {
        $start = new DateTime('2018-06-06');
        $end = new DateTime('2018-06-12');

        // Raw total pageviews.
        static::assertEquals(
            361,
            $this->repo->getPageviews('en.wikipedia', ['Domino_Park'], $start, $end)
        );

        // Multiple pages.
        static::assertEquals(
            93059,
            $this->repo->getPageviews('en.wikipedia', ['Cat', 'Dog'], $start, $end)
        );

        // With average.
        [$total, $avg] = $this->repo->getPageviews(
            'en.wikipedia',
            ['Domino_Park'],
            $start,
            $end,
            31
        );

        // First element should be the same as total pageviews.
        static::assertEquals(361, $total);

        // Average during the period, which should only apply to the days the article existed (June 10 - June 12).
        static::assertEquals(120, $avg);

        $start = new DateTime('2019-02-01');
        $end = new DateTime('2019-02-15');

        // This particular endpoint has gaps in the time series.
        // getPageviews() should fill these in with zeros and give you the correct average.
        // @see https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/wikidata/all-access/user/Q61506256/daily/20190201/20190215
        // @see https://wikitech.wikimedia.org/wiki/Analytics/AQS/Pageviews#Gotchas
        static::assertEquals(
            [12, 1], // 12 total pageviews, divided by 15 days = round(0.8) = 1 average pageview a day.
            $this->repo->getPageviews('www.wikidata', ['Q61506256'], $start, $end, 31)
        );
    }
}
