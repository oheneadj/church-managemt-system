<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\UserObserver;
use App\Traits\BelongsToChurch;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff account. Members are a separate table; staff sign in with phone and OTP only.
 */
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    use BelongsToChurch, HasApiTokens, HasRoles, HasUlids, SoftDeletes;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'phone',
        'email',
        'is_active',
        'last_login_at',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
