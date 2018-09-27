<?php
/**
 * This file contains only the FormatExtension class.
 */

declare(strict_types=1);

namespace AppBundle\Twig;

use AppBundle\Repository\EventWikiRepository;
use DateTime;
use IntlDateFormatter;
use NumberFormatter;

/**
 * The FormatExtension offers various formatters to be used in Twig views.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class FormatExtension extends Extension
{
    /** @var NumberFormatter Instance of NumberFormatter class, used in localizing numbers. */
    protected $numFormatter;

    /** @var IntlDateFormatter Instance of IntlDateFormatter class, used in localizing dates. */
    protected $dateFormatter;

    /**
     * Get the name of this extension.
     * @return string
     */
    public function getName(): string
    {
        return 'format_extension';
    }

    /*********************************** FUNCTIONS ***********************************/

    /**
     * Get all functions that this class provides.
     * @return \Twig_SimpleFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new \Twig_SimpleFunction('formatDuration', [$this, 'formatDuration']),
            new \Twig_SimpleFunction('numberFormat', [$this, 'numberFormat']),
        ];
    }

    /*********************************** FILTERS ***********************************/

    /**
     * Get all filters for this extension.
     * @return \Twig_SimpleFilter[]
     */
    public function getFilters(): array
    {
        return [
            new \Twig_SimpleFilter('ucfirst', [$this, 'ucfirst']),
            new \Twig_SimpleFilter('percent_format', [$this, 'percentFormat']),
            new \Twig_SimpleFilter('diff_format', [$this, 'diffFormat'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('num_format', [$this, 'numberFormat']),
            new \Twig_SimpleFilter('date_localize', [$this, 'dateFormat']),
            new \Twig_SimpleFilter('date_format', [$this, 'dateFormatStd']),
            new \Twig_SimpleFilter('wikify', [$this, 'wikify']),
        ];
    }

    /**
     * Format a number based on language settings.
     * @param int|float $number
     * @param int $decimals Number of decimals to format to.
     * @return string
     */
    public function numberFormat($number, int $decimals = 0): string
    {
        if (!isset($this->numFormatter)) {
            $lang = $this->intuition->getLang();
            $this->numFormatter = new NumberFormatter($lang, NumberFormatter::DECIMAL);
        }

        // Get separator symbols.
        $decimal = $this->numFormatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $thousands = $this->numFormatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);

        $formatted = number_format($number, $decimals, $decimal, $thousands);

        // Remove trailing .0's (e.g. 40.00 -> 40).
        return preg_replace("/\\".$decimal."0+$/", '', $formatted);
    }

    /**
     * Localize the given date based on language settings.
     * @param string|DateTime $datetime
     * @return string
     */
    public function dateFormat($datetime): string
    {
        // If the language is 'en' with no country code,
        // override the US English format that's provided by ICU.
        if ('en' === $this->intuition->getLang()) {
            return $this->dateFormatStd($datetime);
        }

        // Otherwise, format it according to the current locale.
        if (!isset($this->dateFormatter)) {
            $this->dateFormatter = new IntlDateFormatter(
                $this->intuition->getLang(),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::SHORT
            );
        }

        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }

        return $this->dateFormatter->format($datetime);
    }

    /**
     * Format the given date to ISO 8601.
     * @param string|DateTime $datetime
     * @return string
     */
    public function dateFormatStd($datetime): string
    {
        if (is_string($datetime) || is_int($datetime)) {
            $datetime = new DateTime($datetime);
        }

        return $datetime->format('Y-m-d H:i');
    }

    /**
     * Mysteriously missing Twig helper to capitalize only the first character.
     * E.g. used for table headings for translated messages.
     * @param string $str The string
     * @return string The string, capitalized
     */
    public function ucfirst(string $str): string
    {
        return ucfirst($str);
    }

    /**
     * Format a given number or fraction as a percentage.
     * @param int|float $numerator Numerator or single fraction if denominator is omitted.
     * @param int|float $denominator Denominator.
     * @param int $precision Number of decimal places to show.
     * @return string Formatted percentage.
     */
    public function percentFormat($numerator, $denominator = null, int $precision = 1): string
    {
        if (!$denominator) {
            $quotient = $numerator;
        } else {
            $quotient = ($numerator / $denominator) * 100;
        }

        return $this->numberFormat($quotient, $precision).'%';
    }

    /**
     * Format a given number as a diff, colouring it green if it's positive, red if negative, gary if zero.
     * @param int $size Diff size
     * @return string Markup with formatted number
     */
    public function diffFormat(int $size): string
    {
        if ($size < 0) {
            $class = 'diff-neg';
        } elseif ($size > 0) {
            $class = 'diff-pos';
        } else {
            $class = 'diff-zero';
        }

        $size = $this->numberFormat($size);

        return "<span class='$class'>$size</span>";
    }

    /**
     * Format a time duration as humanized string.
     * @param int $seconds Number of seconds.
     * @param bool $translate Used for unit testing. Set to false to return
     *   the value and i18n key, instead of the actual translation.
     * @return string|mixed[] Examples: '30 seconds', '2 minutes', '15 hours', '500 days',
     *   or [30, 'num-seconds'] (etc.) if $translate is false.
     */
    public function formatDuration(int $seconds, bool $translate = true)
    {
        [$val, $key] = $this->getDurationMessageKey($seconds);

        if ($translate) {
            return $this->numberFormat($val).' '.$this->intuition->msg("num-$key", [$val]);
        } else {
            return [$this->numberFormat($val), "num-$key"];
        }
    }

    /**
     * Given a time duration in seconds, generate a i18n message key and value.
     * @param int $seconds Number of seconds.
     * @return mixed[] [int - message value, string - message key]
     */
    private function getDurationMessageKey(int $seconds): array
    {
        /** @var int $val Value to show in message */
        $val = $seconds;

        /** @var string $key Unit of time, used in the key for the i18n message */
        $key = 'seconds';

        if ($seconds >= 86400) {
            // Over a day
            $val = (int)floor($seconds / 86400);
            $key = 'days';
        } elseif ($seconds >= 3600) {
            // Over an hour, less than a day
            $val = (int)floor($seconds / 3600);
            $key = 'hours';
        } elseif ($seconds >= 60) {
            // Over a minute, less than an hour
            $val = (int)floor($seconds / 60);
            $key = 'minutes';
        }

        return [$val, $key];
    }

    /**
     * Convert raw wikitext to HTML-formatted string.
     * @param string $wikitext
     * @param string $domain Project domain such as en.wikipedia
     * @param string $pageTitle Page title including namespace.
     * @return string
     */
    public function wikify(string $wikitext, string $domain, ?string $pageTitle = null): string
    {
        return EventWikiRepository::wikifyString($wikitext, $domain, $pageTitle);
    }
}
