<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when someone tries to check in to a service that is not open for check-in.
 */
class CheckinClosedException extends Exception
{
    /**
     * Answers 409 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Check-in is closed for this service.'], Response::HTTP_CONFLICT);
    }
}
