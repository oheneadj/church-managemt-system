<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a church tries to send a broadcast it cannot pay for (QA-08 TC-08-09). The message says exactly what is needed
 * and what the church has, so the person knows how many credits to get.
 */
class InsufficientBroadcastCreditsException extends Exception
{
    /**
     * Takes the credits the broadcast needs and the credits the church has.
     */
    public function __construct(public readonly int $needed, public readonly int $balance)
    {
        parent::__construct("Insufficient SMS credits. You need {$needed} credits, you have {$balance}. Purchase more credits to proceed.");
    }

    /**
     * Answers 422 with the message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
