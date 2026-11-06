<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when someone who proved they know the password belongs to a deactivated account (D-055). They are told so, and
 * no code is sent. A wrong password for the same account still gets the one generic message, so this reveals nothing to
 * a person who does not know the password.
 */
class AccountDeactivatedException extends Exception
{
    /**
     * Answers 403 with the words the church admin's name is not needed for.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Your account has been deactivated. Contact your church admin.'], Response::HTTP_FORBIDDEN);
    }
}
