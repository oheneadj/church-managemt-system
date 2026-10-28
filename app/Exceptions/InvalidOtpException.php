<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown for a wrong, expired or unknown code. One message for all three cases, so a caller
 * cannot tell whether the phone number exists or how the code failed.
 */
class InvalidOtpException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'The code is invalid or has expired.'], 422);
    }
}
