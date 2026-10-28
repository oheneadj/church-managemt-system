<?php

declare(strict_types=1);

namespace App\DTOs\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lets a DTO read the signed-in staff member from the request. Every route that builds a DTO sits behind
 * auth:sanctum, so a missing user means a routing mistake and is treated as unauthenticated.
 */
trait ResolvesActor
{
    private static function actorFrom(FormRequest $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
