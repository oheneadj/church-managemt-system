<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown at start-up when the configured SMS provider cannot be used: the log driver in production, or a
 * provider whose adapter is not written yet.
 */
class SmsProviderUnavailableException extends RuntimeException
{
    /**
     * Takes the reason, which goes to the log.
     */
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
