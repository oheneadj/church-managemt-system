<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when someone tries to void a gift that was already voided (QA-06 TC-06-12).
 */
class ContributionAlreadyVoidedException extends Exception
{
    /**
     * Answers 422 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'This contribution has already been voided.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
