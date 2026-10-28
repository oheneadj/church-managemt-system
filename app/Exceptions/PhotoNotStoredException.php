<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the storage disk refuses a processed member photo. It is a server error, so the caller sees a generic 500.
 */
class PhotoNotStoredException extends RuntimeException
{
    /**
     * Sets the message that goes to the log.
     */
    public function __construct()
    {
        parent::__construct('The photo could not be stored.');
    }
}
