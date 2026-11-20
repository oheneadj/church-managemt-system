<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * The base class every controller extends. It only holds the helper that reads the signed-in staff member;
 * controllers do nothing else (build a DTO, call one Action, return a Resource).
 */
abstract class Controller
{
    /**
     * The signed-in staff member. Routes that call this sit behind auth:sanctum, so a missing user
     * means a routing mistake and is treated as unauthenticated.
     */
    protected function staff(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
