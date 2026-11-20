<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A payment provider could not be reached, or said it was busy or in trouble (a lost connection, a rate limit, a server error).
 * Trying again later could work, so a job that gets this retries with backoff. Money is never assumed to have moved.
 */
class PaymentProviderUnavailableException extends RuntimeException
{
    /**
     * Holds why, without any personal detail.
     */
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
