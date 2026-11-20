<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a scanned QR code is unknown, expired, or points to a service that is gone. One message for all of them,
 * so a caller learns nothing about which codes exist.
 */
class InvalidQrCodeException extends Exception
{
    /**
     * Answers 404 and tells the member what to do.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'This code has expired. Please scan the screen again.'], Response::HTTP_NOT_FOUND);
    }
}
