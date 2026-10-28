<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * A permission with a ULID primary key, matching the permission tables (D-016).
 */
class Permission extends SpatiePermission
{
    use HasUlids;
}
