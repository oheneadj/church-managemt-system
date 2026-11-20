<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when someone tries to open check-in for a service that is not on today's date (D-052). A service weeks away, or
 * long past, cannot be opened; a missed one can still be given late attendance by hand.
 */
class CheckinOnlyOnServiceDayException extends Exception
{
    /**
     * Answers 409 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Check-in can only be opened on the day of the service.'], Response::HTTP_CONFLICT);
    }
}
