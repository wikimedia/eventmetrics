<?php
/**
 * This file contains only the EventWikiRepository class.
 */

declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use DateTime;
use Doctrine\DBAL\Connection;
use Exception;

/**
 * This class supplies and fetches data for the EventWiki class.
 * @codeCoverageIgnore
 */
class EventWikiRepository extends Repository
{
    /** Max number of pages to be returned when generating page IDs. */
    public const MAX_PAGES = 50000;

    /**
     * Class name of associated entity.
     * Implements Repository::getEntityClass
     * @return string
     */
    public function getEntityClass(): string
    {
        return EventWiki::class;
    }

    /**
     * Get the wiki's domain name without the .org given a database name or domain.
     * @param string $value
     * @return string|null Null if no wiki was found.
     */
    public function getDomainFromEventWikiInput(string $value): ?string
    {
        if ('*.' === substr($value, 0, 2)) {
            $ret = $this->getWikiFamilyName(substr($value, 2));
            return null !== $ret ? '*.'.$ret : null;
        }

        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(['dbname, url'])
            ->from('wiki')
            ->where($rqb->expr()->eq('dbname', ':project'))
            ->orWhere($rqb->expr()->like('url', ':projectUrl'))
            ->orWhere($rqb->expr()->like('url', ':projectUrl2'))
            ->setParameter('project', str_replace('_p', '', $value))
            ->setParameter('projectUrl', "https://$value")
            ->setParameter('projectUrl2', "https://$value.org");
        $ret = $this->executeQueryBuilder($rqb)->fetch();

        // No matches found.
        if (!$ret) {
            return null;
        }

        // Extract and return just the domain name without '.org' suffix.
        $matches = [];
        preg_match('/^https?\:\/\/(.*)\.org$/', $ret['url'], $matches);
        if (isset($matches[1])) {
            return $matches[1];
        } else {
            return null;
        }
    }

    /**
     * This effectively validates the given name as a wiki family
     * (wikipedia, wiktionary, etc). Null is returned if invalid.
     * @param string $value The wiki family name.
     * @return string|null The wiki family name, or null if invalid.
     */
    public function getWikiFamilyName(string $value): ?string
    {
        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(['family'])
            ->from('wiki')
            ->where($rqb->expr()->eq('family', ':family'))
            ->setParameter('family', $value);
        return $this->executeQueryBuilder($rqb)->fetch()['family'];
    }

    /**
     * Get the database name of the given (partial) domain name.
     * @param string $domain The domain name, without trailing '.org'.
     * @return string Null if not found.
     */
    public function getDbNameFromDomain(string $domain): string
    {
        $projectUrl = "https://$domain.org";

        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(["CONCAT(dbname, '_p') AS dbname"])
            ->from('wiki')
            ->where('url = :projectUrl')
            ->setParameter('projectUrl', $projectUrl);

        $row = $this->executeQueryBuilder($rqb)->fetch();
        if (!isset($row['dbname'])) {
            throw new Exception("Unable to determine database name for domain '$domain'.");
        }
        return $row['dbname'];
    }

    /**
     * Public static method to convert wikitext to HTML, can be used on any arbitrary string.
     * Does NOT support section links unless you specify a page.
     * @param string $wikitext
     * @param string $domain The project domain such as en.wikipedia
     * @param string $pageTitle The title of the page, including namespace.
     * @return string
     * @static
     */
    public static function wikifyString(string $wikitext, string $domain, ?string $pageTitle = null): string
    {
        $wikitext = htmlspecialchars(html_entity_decode($wikitext), ENT_NOQUOTES);
        $sectionMatch = null;
        $isSection = preg_match_all("/^\/\* (.*?) \*\//", $wikitext, $sectionMatch);
        $pagePath = "https://$domain.org/wiki/";

        if ($isSection && isset($pageTitle)) {
            $pageUrl = $pagePath.ucfirst(str_replace(' ', '_', $pageTitle));
            $sectionTitle = $sectionMatch[1][0];

            // Must have underscores for the link to properly go to the section.
            $sectionTitleLink = htmlspecialchars(str_replace(' ', '_', $sectionTitle));

            $sectionWikitext = "<a target='_blank' href='$pageUrl#$sectionTitleLink'>&rarr;</a>".
                "<em class='text-muted'>".htmlspecialchars($sectionTitle).":</em> ";
            $wikitext = str_replace($sectionMatch[0][0], trim($sectionWikitext), $wikitext);
        }

        return self::wikifyInternalLinks($wikitext, $domain);
    }

    /**
     * Converts internal links in wikitext to HTML.
     * @param string $wikitext
     * @param string $domain The project domain such as en.wikipedia
     * @return string Updated wikitext.
     * @static
     */
    private static function wikifyInternalLinks(string $wikitext, string $domain): string
    {
        $pagePath = "https://$domain.org/wiki/";
        $linkMatch = null;

        while (preg_match_all("/\[\[:?(.*?)\]\]/", $wikitext, $linkMatch)) {
            $wikiLinkParts = explode('|', $linkMatch[1][0]);
            $wikiLinkPath = htmlspecialchars($wikiLinkParts[0]);
            $wikiLinkText = htmlspecialchars(
                $wikiLinkParts[1] ?? $wikiLinkPath
            );

            // Use normalized page title (underscored, capitalized).
            $pageUrl = $pagePath.ucfirst(str_replace(' ', '_', $wikiLinkPath));
            $link = "<a target='_blank' href='$pageUrl'>$wikiLinkText</a>";
            $wikitext = str_replace($linkMatch[0][0], $link, $wikitext);
        }

        return $wikitext;
    }

    /**
     * Get all available wikis on the replicas, as defined by EventWiki::VALID_WIKI_PATTERN.
     * @return string[] With domain as the keys, database name as the values.
     */
    public function getAvailableWikis(): array
    {
        /** @var string $validWikiRegex Regex-escaped and without surrounding forward slashes. */
        $validWikiRegex = str_replace(
            '\\',
            '\\\\',
            trim(EventWiki::VALID_WIKI_PATTERN, '/')
        );

        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select([
            "REGEXP_REPLACE(url, 'https?:\/\/(.*)\.org', '\\\\1')",
            "CONCAT(dbname, '_p')",
        ])
            ->from('wiki')
            ->where('is_closed = 0')
            ->andWhere("url RLIKE '$validWikiRegex'");

        return $this->executeQueryBuilder($rqb)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /**
     * Get all unique page IDs edited/created within the Event for the given wiki. If you need to do this for pages
     * within specific categories, without participants, use EventCategoryRepository::getPagesInCategories().
     * @param string $dbName
     * @param DateTime $start
     * @param DateTime $end
     * @param string[] $usernames
     * @param string[] $categoryTitles
     * @param string $type Whether only pages 'created' or 'edited' should be returned. Default is to return both.
     *   To get pages improved, first get edited then use array_diff against created.
     * @return int[]
     */
    public function getPageIds(
        string $dbName,
        DateTime $start,
        DateTime $end,
        array $usernames = [],
        array $categoryTitles = [],
        string $type = ''
    ): array {
        if ((empty($usernames) && empty($categoryTitles)) ||
            // No local file uploads unless there are participants.
            ('commonswiki_p' !== $dbName && empty($usernames) && 'files' === $type)
        ) {
            return [];
        }

        // Categories are ignored for local file uploads (non-Commons).
        $shouldUseCategories = count($categoryTitles) > 0 && !('files' === $type && 'commonswiki_p' !== $dbName);

        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');

        $conn = $this->getReplicaConnection();
        $rqb = $conn->createQueryBuilder();

        // Normal `revision` table is faster if you're not filtering by user.
        $revisionTable = $this->getTableName('revision', empty($usernames) ? '' : 'userindex');

        $rqb->select('DISTINCT rev_page')
            ->from("$dbName.$revisionTable")
            ->join("$dbName.$revisionTable", "$dbName.page", 'page_rev', 'page_id = rev_page');

        if ($shouldUseCategories) {
            $rqb->join("$dbName.$revisionTable", "$dbName.categorylinks", 'category_rev', 'cl_from = rev_page')
                ->where('cl_to IN (:categoryTitles)')
                ->setParameter('categoryTitles', $categoryTitles, Connection::PARAM_STR_ARRAY);
        }

        $nsId = 'files' === $type ? 6 : 0;
        $rqb->andWhere("page_namespace = $nsId")
            ->andWhere('page_is_redirect = 0')
            ->andWhere('rev_timestamp BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if (count($usernames) > 0) {
            $rqb->andWhere($rqb->expr()->in('rev_user_text', ':usernames'))
                ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);
        }

        // If only pages created, edited or files are being requested, limit based on the presence of a parent revision.
        if (in_array($type, ['created', 'edited', 'files'])) {
            $typeOperator = 'edited' === $type ? '!=' : '=';
            $rqb->andWhere("rev_parent_id $typeOperator 0");
        }

        $rqb->setMaxResults(self::MAX_PAGES);

        $result = $this->executeQueryBuilder($rqb)->fetchAll(\PDO::FETCH_COLUMN);
        return $result ? array_map('intval', $result) : $result;
    }

    /**
     * Get the total pageviews count for a set of pages, from a given date until today. Optionally reduce to an average
     * of the last N days, where N is Event::AVAILABLE_METRICS['pages-improved-pageviews-avg'].
     * @param string $dbName
     * @param string $domain
     * @param DateTime $start
     * @param int[] $pageIds
     * @param bool $getDailyAverage
     * @return int
     */
    public function getPageviews(
        string $dbName,
        string $domain,
        DateTime $start,
        array $pageIds,
        bool $getDailyAverage = false
    ): int {
        if (0 === count($pageIds)) {
            return 0;
        }

        $pageviewsRepo = $this->getPageviewsRepository();
        $recentDayCount = Event::AVAILABLE_METRICS['pages-improved-pageviews-avg'];
        $end = new DateTime('yesterday midnight');
        $pageviews = 0;

        $stmt = $this->getPageTitles($dbName, $pageIds, true);

        // FIXME: make async requests for pageviews, 200 pages at a time.
        while ($result = $stmt->fetch()) {
            $pageTitle = $result['page_title'];

            if ($getDailyAverage) {
                $pageviews += $pageviewsRepo->getAvgPageviewsPerArticle($domain, $pageTitle, $recentDayCount);
            } else {
                $pageviews += $pageviewsRepo->getPageviewsPerArticle($domain, $pageTitle, $start, $end);
            }
        }

        return $pageviews;
    }

    /**
     * Get page IDs of deleted pages.
     * @param string $dbName
     * @param DateTime $start
     * @param DateTime $end
     * @param string[] $usernames
     * @return int[]
     */
    public function getDeletedPageIds(string $dbName, DateTime $start, DateTime $end, array $usernames = []): array
    {
        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');

        $rqb = $this->getReplicaConnection()->createQueryBuilder();

        // Don't use userindex unless we're given usernames.
        $archiveTable = $this->getTableName('archive', 0 === count($usernames) ? '' : 'userindex');

        $rqb->select('DISTINCT ar_page')
            ->from("$dbName.$archiveTable")
            ->where('ar_namespace = 0')
            ->andWhere('ar_timestamp BETWEEN :start AND :end');

        if (count($usernames) > 0) {
            $rqb->andWhere($rqb->expr()->in('rev_user_text', ':usernames'))
                ->setParameter('usernames', $usernames, Connection::PARAM_STR_ARRAY);
        }

        $rqb->setParameter('start', $start)
            ->setParameter('end', $end);

        $result = $this->executeQueryBuilder($rqb)->fetchAll(\PDO::FETCH_COLUMN);
        return $result ? array_map('intval', $result) : $result;
    }

    /**
     * Get the page titles of the pages with the given IDs.
     * @param string $dbName
     * @param int[] $pageIds
     * @param bool $stmt Whether to get only the statement, so that the calling method can use fetch().
     * @param bool $includePageIds Whether to include page IDs in the result.
     * @return mixed[]|\Doctrine\DBAL\Driver\ResultStatement
     */
    public function getPageTitles(string $dbName, array $pageIds, bool $stmt = false, bool $includePageIds = false)
    {
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $select = $includePageIds ? ['page_id', 'page_title'] : 'page_title';
        $rqb->select($select)
            ->from("$dbName.page")
            ->where($rqb->expr()->in('page_id', ':ids'))
            ->setParameter('ids', $pageIds, Connection::PARAM_INT_ARRAY);
        $result = $this->executeQueryBuilder($rqb);

        return $stmt ? $result : $result->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Calculates the number of bytes changed during an event
     *
     * @param Event $event
     * @param string $dbName
     * @param int[] $pageIds
     * @param string[] $usernames
     * @return int
     */
    public function getBytesChanged(Event $event, string $dbName, array $pageIds, array $usernames): int
    {
        $revisionTable = $this->getTableName('revision');
        $pageTable = $this->getTableName('page');
        if ($usernames) {
            $usernamesCond = 'AND cur.rev_user_text IN (:usernames)';
        } else {
            $usernamesCond = '';
        }

        $after = "SELECT COALESCE(rev_len, 0)
            FROM $dbName.$revisionTable cur
            WHERE rev_page=page_id
              AND rev_timestamp BETWEEN :start AND :end
              {$usernamesCond}
            ORDER BY rev_timestamp DESC
            LIMIT 1";

        $before = "SELECT COALESCE(prev.rev_len, 0)
            FROM $dbName.$revisionTable cur
                LEFT JOIN $dbName.$revisionTable prev ON cur.rev_parent_id=prev.rev_id
            WHERE cur.rev_page=page_id
              AND cur.rev_timestamp BETWEEN :start AND :end
              {$usernamesCond}
            ORDER BY cur.rev_timestamp ASC
            LIMIT 1";

        $outerSql = "SELECT SUM(after) - SUM(before_)
            FROM (
                SELECT ($after) after, ($before) before_
                    FROM $dbName.$pageTable
                    WHERE page_id IN (:pageIds)
                ) t1";

        $res = $this->executeReplicaQueryWithTypes(
            $outerSql,
            [
                'start' => $event->getStartUTC()->format('YmdHis'),
                'end' => $event->getEndUTC()->format('YmdHis'),
                'pageIds' => $pageIds,
                'usernames' => $usernames,
            ],
            [
                'pageIds' => Connection::PARAM_INT_ARRAY,
                'usernames' => Connection::PARAM_STR_ARRAY,
            ]
        );

        return (int)$res->fetchColumn();
    }

    /**
     * Get the list of users participating in an event with no predefined user list
     *
     * @param string $dbName
     * @param int[] $pageIds
     * @param Event $event
     * @return string[]
     */
    public function getUsersFromPageIDs(string $dbName, array $pageIds, Event $event): array
    {
        $revisionTable = $this->getTableName('revision');
        $rqb = $this->getReplicaConnection()->createQueryBuilder();
        $rqb->select('DISTINCT(rev_user_text)')
            ->from("$dbName.$revisionTable")
            ->where('rev_page IN (:pageIds)')
            ->andWhere('rev_user <> 0')
            ->andWhere('rev_timestamp BETWEEN :start AND :end')
            ->setParameter('pageIds', $pageIds, Connection::PARAM_INT_ARRAY)
            ->setParameter('start', $event->getStartUTC()->format('YmdHis'))
            ->setParameter('end', $event->getEndUTC()->format('YmdHis'));

        return $this->executeQueryBuilder($rqb)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get data for a single page, to be included in the Pages Created report.
     * @param string $dbName
     * @param int $pageId
     * @param string $pageTitle
     * @param string[] $usernames
     * @param DateTime $end
     * @return string[]
     */
    public function getSinglePageCreatedData(
        string $dbName,
        int $pageId,
        string $pageTitle,
        array $usernames,
        DateTime $end
    ): array {
        // Use cache if it exists.
        $cacheKey = $this->getCacheKey(func_get_args(), 'pages_created_info');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $end = $end->format('YmdHis');
        $usernamesSql = empty($usernames) ? '' : 'AND rev_user_text IN (:usernames)';

        // Only use revision_userindex when filtering by user.
        $userRevisionTable = empty($usernames) ? 'revision' : 'revision_userindex';

        $sql = "SELECT `metric`, `value` FROM (
                    (
                        SELECT 'creator' AS `metric`, rev_user_text AS `value`
                        FROM $dbName.revision
                        WHERE rev_page = :pageId
                        LIMIT 1
                    ) UNION (
                        SELECT 'edits' AS `metric`, COUNT(*) AS `value`
                        FROM $dbName.$userRevisionTable
                        WHERE rev_page = :pageId
                            AND rev_timestamp <= :end
                            $usernamesSql
                    ) UNION (
                        SELECT 'bytes' AS `metric`, rev_len AS `value`
                        FROM $dbName.$userRevisionTable
                        WHERE rev_page = :pageId
                            AND rev_timestamp <= :end
                            $usernamesSql
                        ORDER BY rev_timestamp DESC
                        LIMIT 1
                    ) UNION (
                        SELECT 'links' AS `metric`, COUNT(*) AS `value`
                        FROM $dbName.pagelinks
                        JOIN $dbName.page ON page_id = pl_from
                        WHERE pl_from_namespace = 0
                            AND pl_namespace = 0
                            AND pl_title = :pageTitle
                            AND page_is_redirect = 0
                    )
                ) t1";

        $ret = $this->executeReplicaQueryWithTypes(
            $sql,
            [
                'pageId' => $pageId,
                'pageTitle' => $pageTitle,
                'usernames' => $usernames,
                'end' => $end,
            ],
            [
                'usernames' => Connection::PARAM_STR_ARRAY,
            ]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Cache for 10 minutes.
        return $this->setCache($cacheKey, $ret, 'PT10M');
    }

    /**
     * Get the data needed for the Pages Created report, for a single EventWiki.
     * @param EventWiki $wiki
     * @param string[] $usernames
     * @return mixed[]
     */
    public function getPagesCreatedData(EventWiki $wiki, array $usernames): array
    {
        if ($wiki->isFamilyWiki()) {
            return [];
        }

        $dbName = $this->getDbNameFromDomain($wiki->getDomain());
        $pageviewsRepo = $this->getPageviewsRepository();
        $avgPageviewsOffset = Event::AVAILABLE_METRICS['pages-improved-pageviews-avg'];
        $pages = $this->getPageTitles($dbName, $wiki->getPagesCreated(), true, true);
        $start = $wiki->getEvent()->getStartUTC();
        $end = $wiki->getEvent()->getEndUTC();
        $now = new DateTime('yesterday midnight');
        $data = [];

        while ($page = $pages->fetch()) {
            // FIXME: async?
            [$pageviews, $avgPageviews] = $pageviewsRepo->getPageviewsPerArticle(
                $wiki->getDomain(),
                $page['page_title'],
                $start,
                $now,
                $avgPageviewsOffset
            );

            $pageInfo = $this->getSinglePageCreatedData(
                $dbName,
                (int)$page['page_id'],
                $page['page_title'],
                $usernames,
                $end
            );

            $data[] = array_merge($pageInfo, [
                'pageTitle' => $page['page_title'],
                'wiki' => $wiki->getDomain(),
                'pageviews' => (int)$pageviews,
                'avgPageviews' => (int)$avgPageviews,
            ]);
        }

        return $data;
    }

    /**
     * Get data for a single page, to be included in the Pages Improved report.
     * @param string $dbName
     * @param int $pageId
     * @param string $pageTitle
     * @param string[] $usernames
     * @param DateTime $start
     * @param DateTime $end
     * @return string[]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSinglePageImprovedData(
        string $dbName,
        int $pageId,
        string $pageTitle,
        array $usernames,
        DateTime $start,
        DateTime $end
    ): array {
        // Use cache if it exists.
        $cacheKey = $this->getCacheKey(func_get_args(), 'pages_improved_info');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $start = $start->format('YmdHis');
        $end = $end->format('YmdHis');
        $usernamesSql = empty($usernames) ? '' : 'AND rev.rev_user_text IN (:usernames)';

        // Only use revision_userindex when filtering by user.
        $userRevisionTable = empty($usernames) ? 'revision' : 'revision_userindex';

        $sql = "SELECT `metric`, `value` FROM (
                    (
                        SELECT 'edits' AS `metric`, COUNT(*) AS `value`
                        FROM $dbName.$userRevisionTable rev
                        WHERE rev_page = :pageId
                            AND rev_timestamp BETWEEN :start AND :end
                            $usernamesSql
                    ) UNION (
                        SELECT 'start_bytes' AS `metric`, COALESCE(prev.rev_len, 0) AS `value`
                            FROM $dbName.$userRevisionTable rev
                                LEFT JOIN $dbName.$userRevisionTable prev ON rev.rev_parent_id=prev.rev_id
                            WHERE rev.rev_page=:pageId
                              AND rev.rev_timestamp BETWEEN :start AND :end
                              {$usernamesSql}
                            ORDER BY rev.rev_timestamp ASC
                            LIMIT 1
                    ) UNION (
                        SELECT 'end_bytes' AS `metric`, COALESCE(rev_len, 0) AS `value`
                            FROM $dbName.$userRevisionTable rev
                            WHERE rev_page=:pageId
                              AND rev_timestamp BETWEEN :start AND :end
                              {$usernamesSql}
                            ORDER BY rev_timestamp DESC
                            LIMIT 1
                    ) UNION (
                        SELECT 'links' AS `metric`, COUNT(*) AS `value`
                        FROM $dbName.pagelinks
                        JOIN $dbName.page ON page_id = pl_from
                        WHERE pl_from_namespace = 0
                            AND pl_namespace = 0
                            AND pl_title = :pageTitle
                            AND page_is_redirect = 0
                    )
                ) t1";

        $rows = $this->executeReplicaQueryWithTypes(
            $sql,
            [
                'pageId' => $pageId,
                'pageTitle' => $pageTitle,
                'usernames' => $usernames,
                'start' => $start,
                'end' => $end,
            ],
            [
                'usernames' => Connection::PARAM_STR_ARRAY,
            ]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $ret = [
            'edits' => $rows['edits'],
            'links' => $rows['links'],
            'bytes' => $rows['end_bytes'] - $rows['start_bytes'],
        ];

        // Cache for 10 minutes.
        return $this->setCache($cacheKey, $ret, 'PT10M');
    }

    /**
     * Get the data needed for the Pages Created report, for a single EventWiki.
     * @param EventWiki $wiki
     * @param string[] $usernames
     * @return mixed[]
     */
    public function getPagesImprovedData(EventWiki $wiki, array $usernames): array
    {
        if ($wiki->isFamilyWiki()) {
            return [];
        }

        $dbName = $this->getDbNameFromDomain($wiki->getDomain());
        $pageviewsRepo = $this->getPageviewsRepository();
        $avgPageviewsOffset = Event::AVAILABLE_METRICS['pages-improved-pageviews-avg'];
        $pages = $this->getPageTitles($dbName, $wiki->getPagesImproved(), true, true);
        $start = $wiki->getEvent()->getStartUTC();
        $end = $wiki->getEvent()->getEndUTC();
        $data = [];

        while ($page = $pages->fetch()) {
            // FIXME: async?
            $avgPageviews = $pageviewsRepo->getAvgPageviewsPerArticle(
                $wiki->getDomain(),
                $page['page_title'],
                $avgPageviewsOffset
            );

            $pageInfo = $this->getSinglePageImprovedData(
                $dbName,
                (int)$page['page_id'],
                $page['page_title'],
                $usernames,
                $start,
                $end
            );

            $data[] = array_merge($pageInfo, [
                'pageTitle' => $page['page_title'],
                'wiki' => $wiki->getDomain(),
                'avgPageviews' => (int)$avgPageviews,
            ]);
        }

        return $data;
    }

    /**
     * Creates and initializes a pageviews repository
     * @return PageviewsRepository
     */
    private function getPageviewsRepository(): PageviewsRepository
    {
        $repo = new PageviewsRepository();
        $repo->setLogger($this->log);
        return $repo;
    }
}
