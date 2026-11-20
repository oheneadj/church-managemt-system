<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when someone tries to cancel a service that already has attendance or gifts recorded at it. That history
 * must stay reachable, so such a service is closed, not cancelled.
 */
class ServiceHasRecordsException extends Exception
{
    /**
     * Answers 409 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'This service already has attendance or gifts recorded, so it cannot be cancelled. Close it instead.'], Response::HTTP_CONFLICT);
    }
}
