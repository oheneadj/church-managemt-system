<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum token with a ULID primary key, matching the personal_access_tokens migration (D-016).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;
}
