<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role with a ULID primary key, matching the permission tables (D-016).
 */
class Role extends SpatieRole
{
    use HasUlids;
}
