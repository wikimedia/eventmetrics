<?php
declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\EventWiki;
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

    /** @var string Base URL for the REST endpoint. */
    protected $endpointUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews';

    /**
     * Given a Mediawiki article and a date range, returns a daily timeseries of its pageview counts.
     * @link https://wikimedia.org/api/rest_v1/#!/Pageviews_data/get_metrics_pageviews_per_article_project_access_agent_article_granularity_start_end
     * @param EventWiki $eventWiki
     * @param string $article Page title with underscores. Will be URL-encoded.
     * @param string $granularity The time unit for the response data, either GRANULARITY_DAILY or GRANULARITY_MONTHLY.
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return string[][]
     */
    public function getPerArticle(
        EventWiki $eventWiki,
        string $article,
        string $granularity,
        DateTime $startTime,
        DateTime $endTime
    ) : array {
        $project = $eventWiki->getDomain().'.org';
        $article = urlencode($article);
        $dateFormat = 'Ymd';
        $start = $startTime->format($dateFormat);
        $end = $endTime->format($dateFormat);
        $url = $this->endpointUrl."/per-article/$project/all-access/user/$article/$granularity/$start/$end";
        return $this->fetch($url);
    }

    /**
     * Given a date range, returns a timeseries of pageview counts. You can filter by project, access method and/or
     * agent type. You can choose between daily and hourly granularity as well.
     * @link https://wikimedia.org/api/rest_v1/#!/Pageviews_data/get_metrics_pageviews_aggregate_project_access_agent_granularity_start_end
     * @param string $granularity The time unit for the response data, one of this class' GRANULARITY_* constants.
     * @param DateTime $startTime The first hour/day/month to include.
     * @param DateTime $endTime The last hour/day/month to include.
     * @param EventWiki $eventWiki The project to query. If not given, pages views on all projects will be returned.
     * @return string[][]
     */
    public function getAggregate(
        string $granularity,
        DateTime $startTime,
        DateTime $endTime,
        ?EventWiki $eventWiki = null
    ) : array {
        $project = $eventWiki ? $eventWiki->getDomain() : 'all-projects';
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
