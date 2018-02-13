<?php
/**
 * This file contains only the EventWikiRepository class.
 */

namespace AppBundle\Repository;

use AppBundle\Model\EventWiki;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

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
    public function getEntityClass()
    {
        return EventWiki::class;
    }

    /**
     * Get the wiki's domain name without the .org given a database name or domain.
     * @param  string $value
     * @return string|null Null if no wiki was found.
     */
    public function getDomainFromEventWikiInput($value)
    {
        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(['dbname, url'])
            ->from('wiki')
            ->where($rqb->expr()->eq('dbname', ':project'))
            ->orwhere($rqb->expr()->like('url', ':projectUrl'))
            ->orwhere($rqb->expr()->like('url', ':projectUrl2'))
            ->setParameter('project', $value)
            ->setParameter('projectUrl', "https://$value")
            ->setParameter('projectUrl2', "https://$value.org");
        $stmt = $rqb->execute();
        $ret = $stmt->fetch();

        $matches = [];
        preg_match('/^https?\:\/\/(.*)\.org$/', $ret['url'], $matches);
        $domain = isset($matches[1]) ? str_replace('www.', '', $matches[1]) : null;

        return $domain;
    }

    /**
     * Get the database name of the given EventWiki.
     * @param  EventWiki $wiki
     * @return string[]
     */
    public function getDbName(EventWiki $wiki)
    {
        $projectUrl = 'https://'.$wiki->getDomain().'.org';

        $conn = $this->getMetaConnection();
        $rqb = $conn->createQueryBuilder();
        $rqb->select(["CONCAT(dbname, '_p') AS dbname"])
            ->from('wiki')
            ->where('url = :projectUrl')
            ->setParameter('projectUrl', $projectUrl);
        $stmt = $rqb->execute();
        return $stmt->fetch()['dbname'];
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
    public static function wikifyString($wikitext, $domain, $pageTitle = null)
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

            $sectionWikitext = "<a target='_blank' href='$pageUrl#$sectionTitleLink'>&rarr;</a>" .
                "<em class='text-muted'>".htmlspecialchars($sectionTitle) . ":</em> ";
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
    private static function wikifyInternalLinks($wikitext, $domain)
    {
        $pagePath = "https://$domain.org/wiki/";
        $linkMatch = null;

        while (preg_match_all("/\[\[:?(.*?)\]\]/", $wikitext, $linkMatch)) {
            $wikiLinkParts = explode('|', $linkMatch[1][0]);
            $wikiLinkPath = htmlspecialchars($wikiLinkParts[0]);
            $wikiLinkText = htmlspecialchars(
                isset($wikiLinkParts[1]) ? $wikiLinkParts[1] : $wikiLinkPath
            );

            // Use normalized page title (underscored, capitalized).
            $pageUrl = $pagePath.ucfirst(str_replace(' ', '_', $wikiLinkPath));
            $link = "<a target='_blank' href='$pageUrl'>$wikiLinkText</a>";
            $wikitext = str_replace($linkMatch[0][0], $link, $wikitext);
        }

        return $wikitext;
    }
}
