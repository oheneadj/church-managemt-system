<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\Church;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * Whose details to return: the signed-in staff member and the church the request came to.
 */
final readonly class ShowCurrentStaffDTO
{
    /**
     * Holds the user and church.
     */
    public function __construct(public User $user, public Church $church) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        return new self($user instanceof User ? $user : throw new AuthenticationException, app('current.church'));
    }
}
