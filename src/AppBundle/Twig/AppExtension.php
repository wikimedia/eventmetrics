<?php
/**
 * This file contains only the AppExtension class.
 */

namespace AppBundle\Twig;

use AppBundle\Repository\EventWikiRepository;
use DateTime;
use IntlDateFormatter;
use NumberFormatter;

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
     * Get an i18n message if the key exists, otherwise treat as plain text.
     * @param string $message
     * @param array $vars
     * @return mixed|null|string
     */
    public function intuitionMessageIfExists($message = '', $vars = [])
    {
        $exists = $this->getIntuition()->msgExists($message, [
            'domain' => 'grantmetrics',
            'variables' => $vars,
        ]);
        if ($exists) {
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
        $messageFiles = glob($this->container->getParameter('kernel.root_dir').'/../i18n/*.json');

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
}
