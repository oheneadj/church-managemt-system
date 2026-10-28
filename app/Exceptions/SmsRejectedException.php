<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an SMS provider refuses a message for good (a bad request, an unapproved sender, no credit).
 * Retrying cannot help, so the job gives up at once. The reason carries the provider's error code and request id,
 * never a phone number or message text.
 */
class SmsRejectedException extends RuntimeException
{
    /**
     * Takes the reason, which goes to the log.
     */
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
