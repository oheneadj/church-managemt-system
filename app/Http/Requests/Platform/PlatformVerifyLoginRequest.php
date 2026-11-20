<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Http\Requests\Auth\VerifyLoginRequest;

/**
 * Validates step two of platform sign-in: an email and the emailed code, with the same rules as church staff's.
 */
class PlatformVerifyLoginRequest extends VerifyLoginRequest {}
