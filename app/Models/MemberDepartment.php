<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\MemberDepartmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pivot row linking a member to a department, with their role there.
 *
 * @property string $department_id
 * @property string $member_id
 * @property string|null $role
 * @property CarbonImmutable|null $joined_at
 */
class MemberDepartment extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<MemberDepartmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'member_id',
        'department_id',
        'role',
        'joined_at',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_date',
        ];
    }

    /**
     * The member this member department belongs to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The department this member department belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
