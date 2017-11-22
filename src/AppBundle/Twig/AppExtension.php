<?php
/**
 * This file contains only the AppExtension class.
 */

namespace AppBundle\Twig;

use NumberFormatter;
use IntlDateFormatter;
use DateTime;

/**
 * Various Twig functions and filters.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class AppExtension extends Extension
{
    /** @var NumberFormatter Instance of NumberFormatter class, used in localizing numbers. */
    protected $numFormatter;

    /** @var IntlDateFormatter Instance of IntlDateFormatter class, used in localizing dates. */
    protected $dateFormatter;

    /** @var float Duration of the current HTTP request in seconds. */
    protected $requestTime;

    /**
     * Get the name of this extension.
     * @return string
     */
    public function getName()
    {
        return 'app_extension';
    }

    /*********************************** FUNCTIONS ***********************************/

    /**
     * Get all functions that this class provides.
     * @return array
     */
    public function getFunctions()
    {
        $options = ['is_safe' => ['html']];
        return [
            new \Twig_SimpleFunction('loggedInUser', [$this, 'loggedInUser']),
            new \Twig_SimpleFunction('msg', [$this, 'intuitionMessage'], $options),
            new \Twig_SimpleFunction('msgIfExists', [$this, 'intuitionMessageIfExists'], $options),
            new \Twig_SimpleFunction('lang', [$this, 'getLang'], $options),
            new \Twig_SimpleFunction('langName', [$this, 'getLangName'], $options),
            new \Twig_SimpleFunction('allLangs', [$this, 'getAllLangs']),
            new \Twig_SimpleFunction('isRTL', [$this, 'intuitionIsRTL']),
            new \Twig_SimpleFunction('isRTLLang', [$this, 'intuitionIsRTLLang']),
            new \Twig_SimpleFunction('shortHash', [$this, 'gitShortHash']),
            new \Twig_SimpleFunction('hash', [$this, 'gitHash']),
            new \Twig_SimpleFunction('formatDuration', [$this, 'formatDuration']),
            new \Twig_SimpleFunction('numberFormat', [$this, 'numberFormat']),
        ];
    }

    /**
     * Get the currently logged in user's details.
     * @return string[]
     */
    public function loggedInUser()
    {
        return $this->container->get('session')->get('logged_in_user');
    }

    /**
     * Get an i18n message.
     * @param string $message
     * @param array $vars
     * @return mixed|null|string
     */
    public function intuitionMessage($message = '', $vars = [])
    {
        if (is_array($message)) {
            $vars = $message;
            $message = $message[0];
            $vars = array_slice($vars, 1);
        }
        return $this->getIntuition()->msg($message, [
            'domain' => 'grantmetrics',
            'variables' => $vars
        ]);
    }

    /**
     * Get an i18n message if the key exists, otherwise treat as plain text.
     * @param string $message
     * @param array $vars
     * @return mixed|null|string
     */
    public function intuitionMessageIfExists($message = '', $vars = [])
    {
        if ($this->getIntuition()->msgExists($message)) {
            return $this->intuitionMessage($message, $vars);
        } else {
            return $message;
        }
    }

    /**
     * Get the current language code.
     * @return string
     */
    public function getLang()
    {
        return $this->getIntuition()->getLang();
    }

    /**
     * Get the current language name (defaults to 'English').
     * @return string
     */
    public function getLangName()
    {
        return in_array(ucfirst($this->getIntuition()->getLangName()), $this->getAllLangs())
            ? $this->getIntuition()->getLangName()
            : 'English';
    }

    /**
     * Get all available languages in the i18n directory.
     * @return array Associative array of langKey => langName
     */
    public function getAllLangs()
    {
        $messageFiles = glob($this->container->getParameter('kernel.root_dir') . '/../i18n/*.json');

        $languages = array_values(array_unique(array_map(
            function ($filename) {
                return basename($filename, '.json');
            },
            $messageFiles
        )));

        $availableLanguages = [];

        foreach ($languages as $lang) {
            $availableLanguages[$lang] = ucfirst($this->getIntuition()->getLangName($lang));
        }
        asort($availableLanguages);

        return $availableLanguages;
    }

    /**
     * Whether the current language is right-to-left.
     * @return bool
     */
    public function intuitionIsRTL()
    {
        return $this->getIntuition()->isRTL($this->getIntuition()->getLang());
    }

    /**
     * Whether the given language is right-to-left.
     * @param string $lang The language code.
     * @return bool
     */
    public function intuitionIsRTLLang($lang)
    {
        return $this->getIntuition()->isRTL($lang);
    }

    /**
     * Get the short hash of the currently checked-out Git commit.
     * @return string
     */
    public function gitShortHash()
    {
        return exec('git rev-parse --short HEAD');
    }

    /**
     * Get the full hash of the currently checkout-out Git commit.
     * @return string
     */
    public function gitHash()
    {
        return exec('git rev-parse HEAD');
    }


    /*********************************** FILTERS ***********************************/

    /**
     * Get all filters for this extension.
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('capitalize_first', [$this, 'capitalizeFirst']),
            new \Twig_SimpleFilter('percent_format', [$this, 'percentFormat']),
            new \Twig_SimpleFilter('diff_format', [$this, 'diffFormat'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('num_format', [$this, 'numberFormat']),
            new \Twig_SimpleFilter('date_format', [$this, 'dateFormat']),
        ];
    }

    /**
     * Format a number based on language settings.
     * @param  int|float $number
     * @param  int $decimals Number of decimals to format to.
     * @return string
     */
    public function numberFormat($number, $decimals = 0)
    {
        if (!isset($this->numFormatter)) {
            $lang = $this->getIntuition()->getLang();
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
     * @param  string|DateTime $datetime
     * @return string
     */
    public function dateFormat($datetime)
    {
        if (!isset($this->dateFormatter)) {
            $this->dateFormatter = new IntlDateFormatter(
                $this->getIntuition()->getLang(),
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
     * Mysteriously missing Twig helper to capitalize only the first character.
     * E.g. used for table headings for translated messages.
     * @param  string $str The string
     * @return string      The string, capitalized
     */
    public function capitalizeFirst($str)
    {
        return ucfirst($str);
    }

    /**
     * Format a given number or fraction as a percentage.
     * @param  number  $numerator   Numerator or single fraction if denominator is ommitted.
     * @param  number  $denominator Denominator.
     * @param  integer $precision   Number of decimal places to show.
     * @return string               Formatted percentage.
     */
    public function percentFormat($numerator, $denominator = null, $precision = 1)
    {
        if (!$denominator) {
            $quotient = $numerator;
        } else {
            $quotient = ( $numerator / $denominator ) * 100;
        }

        return $this->numberFormat($quotient, $precision) . '%';
    }

    /**
     * Format a given number as a diff, colouring it green if it's postive, red if negative, gary if zero
     * @param  number $size Diff size
     * @return string       Markup with formatted number
     */
    public function diffFormat($size)
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
     * @return string|array Examples: '30 seconds', '2 minutes', '15 hours', '500 days',
     *   or [30, 'num-seconds'] (etc.) if $translate is false.
     */
    public function formatDuration($seconds, $translate = true)
    {
        list($val, $key) = $this->getDurationMessageKey($seconds);

        if ($translate) {
            return $this->numberFormat($val) . ' ' . $this->intuitionMessage("num-$key", [$val]);
        } else {
            return [$this->numberFormat($val), "num-$key"];
        }
    }

    /**
     * Given a time duration in seconds, generate a i18n message key and value.
     * @param  int $seconds Number of seconds.
     * @return array [int - message value, string - message key]
     */
    private function getDurationMessageKey($seconds)
    {
        /** @var int Value to show in message */
        $val = $seconds;

        /** @var string Unit of time, used in the key for the i18n message */
        $key = 'seconds';

        if ($seconds >= 86400) {
            // Over a day
            $val = (int) floor($seconds / 86400);
            $key = 'days';
        } elseif ($seconds >= 3600) {
            // Over an hour, less than a day
            $val = (int) floor($seconds / 3600);
            $key = 'hours';
        } elseif ($seconds >= 60) {
            // Over a minute, less than an hour
            $val = (int) floor($seconds / 60);
            $key = 'minutes';
        }

        return [$val, $key];
    }
}
