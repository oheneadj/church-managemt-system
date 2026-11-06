<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a broadcast would reach nobody (no member matches, or those who match have SMS turned off or no phone).
 */
class NoBroadcastRecipientsException extends Exception
{
    /**
     * Answers 422 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'No members match: there is nobody to send this to.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
