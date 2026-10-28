<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown while a phone number is locked out after too many wrong codes.
 */
class TooManyOtpAttemptsException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => 'Too many attempts. Try again in '.(int) ceil(config('otp.lockout_seconds') / 60).' minutes.'],
            429,
            ['Retry-After' => (string) config('otp.lockout_seconds')],
        );
    }
}
