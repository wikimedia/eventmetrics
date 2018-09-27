<?php
/**
 * This file contains only the EventWikiRepository class.
 */

declare(strict_types=1);

namespace AppBundle\Repository;

use AppBundle\Model\EventWiki;

/**
 * This class supplies and fetches data for the EventWiki class.
 * @codeCoverageIgnore
 */
class EventWikiRepository extends Repository
{
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
            ->setParameter('project', $value)
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
        if (isset($matches[1]) && preg_match(EventWiki::getValidPattern(), $matches[1])) {
            return $matches[1];
        } else {
            // Entity will be considered invalid and won't be saved.
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
     * Get the database name of the given EventWiki.
     * @param string $domain
     * @return string
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

        return $this->executeQueryBuilder($rqb)
            ->fetch()['dbname'];
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
}
