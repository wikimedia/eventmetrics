<?php
declare(strict_types=1);

namespace AppBundle\Repository;

use DateInterval;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * A PageviewsRepository is used to fetch data from the pageviews API.
 * @link https://wikimedia.org/api/rest_v1/#/Pageviews_data
 */
class PageviewsRepository
{

    public const GRANULARITY_HOURLY = 'hourly';

    public const GRANULARITY_DAILY = 'daily';

    public const GRANULARITY_MONTHLY = 'monthly';

    /** @var string YYYY-MM-DD format of the earliest data available in the Pageviews API. */
    public const MINIMUM_START_DATE = '2015-07-01';

    /** @var string Base URL for the REST endpoint. */
    protected $endpointUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews';

    /**
     * Given a Mediawiki article and a date range, returns a daily timeseries of its pageview counts.
     * @link https://wikimedia.org/api/rest_v1/#!/Pageviews_data/get_metrics_pageviews_per_article_project_access_agent_article_granularity_start_end
     * @param string $domain
     * @param string $article Page title with underscores. Will be URL-encoded.
     * @param string $granularity The time unit for the response data, either GRANULARITY_DAILY or GRANULARITY_MONTHLY.
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return string[][]
     */
    public function getPerArticle(
        string $domain,
        string $article,
        string $granularity,
        DateTime $startTime,
        DateTime $endTime
    ) : array {
        $article = urlencode($article);
        $dateFormat = 'Ymd';
        $start = $startTime->format($dateFormat);
        $end = $endTime->format($dateFormat);
        $url = $this->endpointUrl."/per-article/$domain/all-access/user/$article/$granularity/$start/$end";
        return $this->fetch($url);
    }

    /**
     * Get the sum of daily pageviews for the given article and date range.
     * @param string $domain
     * @param string $pageTitle
     * @param DateTime $start
     * @param DateTime $end
     * @param int|null $avgDaysOffset Specifies the number of days over which to compute the daily average pageviews.
     * @see PageviewsRepository::getPageviewsPerArticle() if you only need the average.
     * @return int|int[]|null Sum of pageviews, or [sum of pageviews, average],
     *   or null if no data was found (could be new article, 404, etc.).
     */
    public function getPageviewsPerArticle(
        string $domain,
        string $pageTitle,
        DateTime $start,
        DateTime $end,
        ?int $avgDaysOffset = null
    ) {
        $pageviewsInfo = $this->getPerArticle(
            $domain,
            $pageTitle,
            PageviewsRepository::GRANULARITY_DAILY,
            $start,
            $end
        );

        if (!isset($pageviewsInfo['items'])) {
            return null;
        }

        $avgOffsetDate = (clone $end)->sub(new DateInterval('P'.(int)$avgDaysOffset.'D'));
        $lastAvgDate = null;
        $pageviews = 0;
        $recentPageviews = 0;

        foreach (array_reverse($pageviewsInfo['items']) as $item) {
            $date = DateTime::createFromFormat('YmdHi', $item['timestamp'].'00');
            if ($date >= $avgOffsetDate) {
                $recentPageviews += $item['views'];
                $lastAvgDate = $date;
            }
            $pageviews += $item['views'];
        }

        if (is_int($avgDaysOffset)) {
            if (null === $lastAvgDate) {
                // No recent pageviews, so average is 0.
                return [$pageviews, 0];
            }
            $numDays = $end->diff($lastAvgDate)->days + 1; // +1 because dates are inclusive.
            return [$pageviews, (int)round($recentPageviews / $numDays)];
        }

        return $pageviews;
    }

    /**
     * Get the sum of daily pageviews for the given article and date range.
     * @param string $domain
     * @param string $pageTitle
     * @param int $offset Specifies the number of days from today over which to compute the daily average pageviews.
     * @return int|null
     */
    public function getAvgPageviewsPerArticle(
        string $domain,
        string $pageTitle,
        int $offset = 30
    ) : ?int {
        $end = new DateTime('yesterday midnight');
        $start = (clone $end)->sub(new DateInterval('P'.$offset.'D'));
        return $this->getPageviewsPerArticle($domain, $pageTitle, $start, $end, $offset)[1] ?? null;
    }

    /**
     * Given a date range, returns a timeseries of pageview counts. You can filter by project, access method and/or
     * agent type. You can choose between daily and hourly granularity as well.
     * @link https://wikimedia.org/api/rest_v1/#!/Pageviews_data/get_metrics_pageviews_aggregate_project_access_agent_granularity_start_end
     * @param string $granularity The time unit for the response data, one of this class' GRANULARITY_* constants.
     * @param DateTime $startTime The first hour/day/month to include.
     * @param DateTime $endTime The last hour/day/month to include.
     * @param string|null $domain The project to query. If not given, pages views on all projects will be returned.
     * @return string[][]
     */
    public function getAggregate(
        string $granularity,
        DateTime $startTime,
        DateTime $endTime,
        ?string $domain = null
    ) : array {
        $project = $domain ?? 'all-projects';
        $dateFormat = 'Ymdh';
        $start = $startTime->format($dateFormat);
        $end = $endTime->format($dateFormat);
        $url = $this->endpointUrl."/aggregate/$project/all-access/$granularity/user/$start/$end";
        return $this->fetch($url);
    }

    /**
     * Fetch and decode an API response.
     * @param string $url
     * @return string[][]
     */
    protected function fetch(string $url) : array
    {
        $client = new Client();
        try {
            $response = $client->get($url);
        } catch (ClientException $exception) {
            return [];
        }
        if (200 !== $response->getStatusCode()) {
            // Discard errors (such as monthly granularity with no full month specified).
            return [];
        }
        $responseJson = $response->getBody()->read($response->getBody()->getSize());
        return json_decode($responseJson, true);
    }
}
