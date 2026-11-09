<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A payment provider refused a request for good (a bad number, a refused account, a wrong key, a retry with a different body).
 * Trying again would only be refused again, so a job that gets this does not retry. The message holds the status, the error
 * code and the request id, never a phone number, a name or an amount.
 */
class PaymentRejectedException extends RuntimeException
{
    /**
     * Holds why, without any personal detail.
     */
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
