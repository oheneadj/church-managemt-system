<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Http\Requests\Auth\LoginRequest;

/**
 * Validates step one of platform sign-in: an email and a password, with the same rules as church staff's.
 */
class PlatformLoginRequest extends LoginRequest {}
