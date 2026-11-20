<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when WhatsApp refuses a message for good (a bad request, a closed 24-hour window, a blocked number).
 * Retrying cannot help, so the job gives up at once. The reason carries Meta's error code, never a phone number or text.
 */
class WhatsAppRejectedException extends RuntimeException
{
    /**
     * Takes the reason, which goes to the log.
     */
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
