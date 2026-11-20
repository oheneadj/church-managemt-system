<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A platform owner's account (D-069): someone who runs the platform itself, apart from every church. It has no church and no
 * roles, and signs in on the platform host with its own guard, so a church's staff can never become one and one can never
 * act inside a church.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 */
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'is_active', 'last_login_at'];

    /** @var list<string> */
    protected $hidden = ['password'];

    /**
     * The type each column is cast to. The password is hashed whenever it is set.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
