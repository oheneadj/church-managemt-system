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
 * @property string $id
 * @property string $church_id
 * @property string $name
 * @property string|null $description
 * @property string|null $head_user_id
 * @property bool $is_active
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
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The user this department belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * The member records this department is linked to.
     *
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_departments', 'department_id', 'member_id')->withPivot('role', 'joined_at')->withTimestamps()->wherePivotNull('deleted_at');
    }
}
