<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown for a wrong, expired or unknown code. One message for all three cases (QA-01 TC-01-03),
 * so a caller cannot tell whether the phone number exists or how the code failed.
 */
class InvalidOtpException extends Exception
{
    /**
     * Answers 422 with one message for every kind of wrong code.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Invalid code'], 422);
    }
}
