<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when a member is added before the church has chosen its member number prefix (D-030).
 */
class MemberNumberPrefixMissingException extends Exception
{
    /**
     * Answers 422 asking the admin to set the prefix first.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Set the church member number prefix before adding members.'], 422);
    }
}
