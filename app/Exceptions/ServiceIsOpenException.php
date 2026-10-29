<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when someone tries to cancel a service that is still open for check-in. Members can check in to it by QR code
 * or WhatsApp, so check-in has to be closed first.
 */
class ServiceIsOpenException extends Exception
{
    /**
     * Answers 409 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Check-in is still open for this service. Close it before cancelling.'], Response::HTTP_CONFLICT);
    }
}
