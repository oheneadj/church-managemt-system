<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\UserObserver;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff account. Members are a separate table. Staff sign in with email, password and an emailed code (D-054); the
 * phone number is a contact number and is not used to sign in.
 *
 * @property string $id
 * @property string $church_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $password the hash, or null until the person sets a password
 * @property CarbonImmutable|null $email_verified_at
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 */
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    use BelongsToChurch, HasApiTokens, HasRoles, HasUlids, SoftDeletes;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['password'];

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
            'password' => 'hashed',
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    /**
     * The departments this staff member heads.
     *
     * @return HasMany<Department, $this>
     */
    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    /**
     * The members this staff member is the assigned pastor of.
     *
     * @return HasMany<Member, $this>
     */
    public function assignedMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'assigned_pastor_id');
    }
}
