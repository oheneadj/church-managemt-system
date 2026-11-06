<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a change would leave a church with no active church admin: removing the role from, or deactivating, the
 * last one (D-055 point 7). Nobody could then manage staff, so the change is refused.
 */
class LastChurchAdminException extends Exception
{
    /**
     * Answers 409 with a plain message.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'A church needs at least one active church admin.'], Response::HTTP_CONFLICT);
    }
}
