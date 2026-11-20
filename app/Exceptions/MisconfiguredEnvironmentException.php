<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown at start-up when a deployed environment is missing a setting or has an unsafe one (php artisan env:check).
 */
class MisconfiguredEnvironmentException extends RuntimeException
{
    /**
     * Takes the list of problems found, joined into one message for the log.
     *
     * @param  list<string>  $problems
     */
    public function __construct(array $problems)
    {
        parent::__construct('The environment is misconfigured: '.implode(' ', $problems));
    }
}
