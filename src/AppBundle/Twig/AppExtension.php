<?php
/**
 * This file contains only the AppExtension class.
 */

declare(strict_types=1);

namespace AppBundle\Twig;

use IntlDateFormatter;
use NumberFormatter;
use Twig\TwigFunction;

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
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'app_extension';
    }

    /*********************************** FUNCTIONS ***********************************/

    /**
     * Get all functions that this class provides.
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('isAdmin', [$this, 'isAdmin']),
        ];
    }

    /**
     * Get the currently logged in user's details.
     * @return string[]
     */
    public function loggedInUser(): array
    {
        return $this->container->get('session')->get('logged_in_user');
    }

    /**
     * Is the logged in user an admin?
     * @return boolean
     * This is tested via EventControllerTest, validating delete buttons have the correct CSS
     * class, but for some reason the clover system doesn't detect that this bit of code was ran.
     * @codeCoverageIgnore
     */
    public function isAdmin(): bool
    {
        return in_array(
            $this->container->get('session')->get('logged_in_user')->username,
            $this->container->getParameter('app.admins')
        );
    }
}
