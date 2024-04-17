<?php
declare( strict_types=1 );

namespace App\Repository;

use DateInterval;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * A PageviewsRepository is used to fetch data from the pageviews API.
 * @link https://wikimedia.org/api/rest_v1/#/Pageviews_data
 */
class PageviewsRepository {
	use LoggerAwareTrait;

	public const GRANULARITY_HOURLY = 'hourly';
	public const GRANULARITY_DAILY = 'daily';
	public const GRANULARITY_MONTHLY = 'monthly';

	/** YYYY-MM-DD format of the earliest data available in the Pageviews API. */
	public const MINIMUM_START_DATE = '2015-07-01';

	private const REQUEST_TIMEOUT = 3;
	private const CONNECT_TIMEOUT = 1.5;
	private const RETRIES = 3;
	private const REQUEST_DELAY = 100;

	/** @var string Base URL for the REST endpoint. */
	protected string $endpointUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews';

	/** @var Client The GuzzleHttp client. */
	private Client $client;

	public function __construct() {
		$this->client = new Client( [
			'timeout' => self::REQUEST_TIMEOUT,
			'connect_timeout' => self::CONNECT_TIMEOUT,
			'delay' => self::REQUEST_DELAY,
			'handler' => $this->getRetryHandler(),
		] );
		$this->logger = new NullLogger();
	}

	/**
	 * Retry handler for the Guzzle Client.
	 * @return HandlerStack
	 */
	private function getRetryHandler(): HandlerStack {
		$handlerStack = HandlerStack::create( new CurlMultiHandler() );
		$handlerStack->push( Middleware::retry( function (
			int $retry,
			Request $request,
			?Response $response,
			?RequestException $exception
		) {
			if ( $response !== null ) {
				// Request succeeded
				return false;
			}

			$url = $request->getUri()->getPath();
			$reason = $exception ? $exception->getMessage() : 'Unknown';

			if ( $retry < self::RETRIES ) {
				$this->logger->notice(
					'Attempt {retry}/{max} to fetch {url} failed: {reason}',
					[
						'retry' => $retry,
						'max' => self::RETRIES,
						'url' => $url,
						'reason' => $reason,
					]
				);
				return true;
			}

			$this->logger->error(
				'Fetching {url} failed after {retry} retries: {reason}',
				[
					'url' => $url,
					'retry' => $retry,
					'reason' => $reason,
				]
			);

			return false;
		} ) );

		return $handlerStack;
	}

	/**
	 * Get the combined pageviews, and optionally average pageviews, of the given articles.
	 * @see PageviewsRepository::getAvgPageviews() if you only need an average.
	 * @param string $domain
	 * @param string[] $pageTitles
	 * @param DateTime $start
	 * @param DateTime $end
	 * @param int|null $avgDaysOffset Specifies the number of days over which to compute the daily average pageviews.
	 * @return int|int[] Sum of pageviews, or [sum of pageviews, average] if $avgDaysOffset is given.
	 */
	public function getPageviews(
		string $domain,
		array $pageTitles,
		DateTime $start,
		DateTime $end,
		?int $avgDaysOffset = null
	): int|array {
		$promises = [];

		foreach ( $pageTitles as $pageTitle ) {
			$promises[] = $this->get( $domain, $pageTitle, self::GRANULARITY_DAILY, $start, $end );
		}
		$responses = Utils::settle( $promises )->wait();

		$totalPageviews = 0;
		$totalAvgPageviews = 0;

		$avgOffsetDate = $avgDaysOffset
			? ( clone $end )->sub( new DateInterval( 'P' . (int)$avgDaysOffset . 'D' ) )
			: null;

		foreach ( $responses as $response ) {
			if ( $response['state'] !== 'fulfilled' ) {
				// Do nothing, API didn't have data most likely.
			} else {
				/** @var Response $value */
				$value = $response['value'];
				$result = json_decode( $value->getBody()->getContents(), true );

				if ( $avgOffsetDate ) {
					[ $pageviews, $avgPageviews ] = $this->processResponse( $result, $end, $avgOffsetDate );
					$totalPageviews += $pageviews;
					$totalAvgPageviews += $avgPageviews;
				} else {
					$totalPageviews += $this->processResponse( $result, $end );
				}
			}
		}

		if ( $avgDaysOffset !== null ) {
			return [ $totalPageviews, $totalAvgPageviews ];
		} else {
			return $totalPageviews;
		}
	}

	/**
	 * Get the sum of the average of pageviews for the given articles.
	 * @param string $domain
	 * @param string[] $pageTitles
	 * @param int $offset Specifies the number of days from today over which to compute the daily average pageviews.
	 * @return int
	 */
	public function getAvgPageviews( string $domain, array $pageTitles, int $offset = 30 ): int {
		$end = new DateTime( 'yesterday midnight' );
		$start = ( clone $end )->sub( new DateInterval( 'P' . $offset . 'D' ) );
		return $this->getPageviews( $domain, $pageTitles, $start, $end, $offset )[1];
	}

	/**
	 * Given a Mediawiki article and a date range, returns a daily timeseries of its pageview counts.
	 * @link https://wikimedia.org/api/rest_v1/#!/Pageviews_data/get_metrics_pageviews_per_article_project_access_agent_article_granularity_start_end
	 * @param string $domain
	 * @param string $article Page title with underscores. Will be URL-encoded.
	 * @param string $granularity The time unit for the response data, either GRANULARITY_DAILY or GRANULARITY_MONTHLY.
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @return PromiseInterface
	 */
	public function get(
		string $domain,
		string $article,
		string $granularity,
		DateTime $startTime,
		DateTime $endTime
	): PromiseInterface {
		$article = urlencode( $article );
		$dateFormat = 'Ymd';
		$start = $startTime->format( $dateFormat );
		$end = $endTime->format( $dateFormat );
		$url = $this->endpointUrl . "/per-article/$domain/all-access/user/$article/$granularity/$start/$end";
		return $this->client->getAsync( $url );
	}

	/**
	 * Parse the given Pageviews API response, returning the sum, and average if requested.
	 * @param array[][] $response
	 * @param DateTime $end
	 * @param DateTime|null $avgOffsetDate Date from which to compute average.
	 * @return int|int[] Sum of pageviews, or [sum of pageviews, average] if $avgOffsetDate is given.
	 */
	private function processResponse( array $response, DateTime $end, ?DateTime $avgOffsetDate = null ): int|array {
		if ( !isset( $response['items'] ) ) {
			return 0;
		}

		$lastAvgDate = null;
		$pageviews = 0;
		$recentPageviews = 0;

		foreach ( array_reverse( $response['items'] ) as $item ) {
			$date = DateTime::createFromFormat( 'YmdHi', $item['timestamp'] . '00' );
			if ( $avgOffsetDate && $date >= $avgOffsetDate ) {
				$recentPageviews += $item['views'];
				$lastAvgDate = $date;
			}
			$pageviews += $item['views'];
		}

		if ( $avgOffsetDate !== null ) {
			if ( $lastAvgDate === null ) {
				// No recent pageviews, so average is 0.
				return [ $pageviews, 0 ];
			}
			// +1 because dates are inclusive.
			$numDays = $end->diff( $lastAvgDate )->days + 1;
			return [ $pageviews, (int)round( $recentPageviews / $numDays ) ];
		}

		return $pageviews;
	}
}
