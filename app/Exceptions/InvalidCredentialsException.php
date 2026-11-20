<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown for a wrong password, an unknown email or an account with no password. One message for all three (D-054), so a
 * caller cannot tell whether an email belongs to a staff member.
 */
class InvalidCredentialsException extends Exception
{
    /**
     * Answers 422 with one message for every kind of failed password check.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Invalid email or password'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
