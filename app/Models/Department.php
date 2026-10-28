<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToChurch;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A ministry department such as the choir or ushers.
 *
 * @property Pivot|null $pivot the member_departments row, when loaded through a member
 */
class Department extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'description',
        'head_user_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_departments', 'department_id', 'member_id')->withPivot('role', 'joined_at')->withTimestamps()->wherePivotNull('deleted_at');
    }
}
