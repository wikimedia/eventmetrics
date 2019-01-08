<?php
/**
 * This file contains only the WebProcessorMonolog class.
 */

declare(strict_types=1);

namespace AppBundle\Logger;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WebProcessorMonolog extends information included in error reporting.
 */
class WebRequestProcessor
{
    /** @var RequestStack The request stack. */
    private $requestStack;

    /**
     * WebProcessorMonolog constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Adds extra information to the log entry.
     * @see https://symfony.com/doc/current/logging/processors.html
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $record['extra']['host'] = $request->getHost();
            $record['extra']['uri'] = $request->getUri();
        }

        return $record;
    }
}
