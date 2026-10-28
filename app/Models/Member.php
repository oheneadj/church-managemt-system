<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person the church knows about, from prospect to active member.
 *
 * @property string $id
 * @property string $church_id
 * @property string $member_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property Gender $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property string $phone
 * @property string|null $address
 * @property MaritalStatus|null $marital_status
 * @property EducationalLevel|null $educational_level
 * @property MembershipType $membership_type
 * @property CarbonImmutable|null $membership_date
 * @property MemberStatus $status
 * @property CarbonImmutable|null $status_changed_at
 * @property string|null $family_id
 * @property FamilyRole|null $family_role
 * @property string $created_by
 * @property CarbonImmutable|null $created_at
 */
class Member extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'member_number',
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'gender',
        'date_of_birth',
        'phone',
        'phone_secondary',
        'email',
        'address',
        'nearest_landmark',
        'digital_address',
        'photo_url',
        'national_id_type',
        'national_id_number',
        'marital_status',
        'occupation',
        'tribe',
        'language_preference',
        'educational_level',
        'membership_type',
        'membership_date',
        'status',
        'status_changed_at',
        'status_note',
        'family_id',
        'family_role',
        'assigned_pastor_id',
        'whatsapp_opted_in',
        'sms_opted_in',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'immutable_date',
            'address' => 'encrypted',
            'national_id_number' => 'encrypted',
            'marital_status' => MaritalStatus::class,
            'educational_level' => EducationalLevel::class,
            'membership_type' => MembershipType::class,
            'membership_date' => 'immutable_date',
            'status' => MemberStatus::class,
            'status_changed_at' => 'immutable_datetime',
            'family_role' => FamilyRole::class,
            'whatsapp_opted_in' => 'boolean',
            'sms_opted_in' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedPastor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pastor_id');
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'member_departments', 'member_id', 'department_id')->withPivot('role', 'joined_at')->withTimestamps()->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<MemberMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(MemberMilestone::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Limits the directory to what this staff member may see (docs/03 roles table, D-031). The admin, pastor
     * and finance secretary see everyone. Otherwise a department head sees the members of departments
     * they head, and a youth pastor sees youth members. A user holding both roles sees both.
     *
     * @param  Builder<Member>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['church-admin', 'pastor', 'finance-secretary'])) {
            return;
        }

        $query->where(function (Builder $visible) use ($user): void {
            if ($user->hasRole('department-head')) {
                $visible->orWhereHas('departments', fn (Builder $d) => $d->where('departments.head_user_id', $user->getKey()));
            }

            if ($user->hasRole('youth-pastor')) {
                $youth = array_values(array_map(
                    fn (EducationalLevel $level) => $level->value,
                    array_filter(EducationalLevel::cases(), fn (EducationalLevel $level) => $level->isYouth()),
                ));
                $visible->orWhereIn('educational_level', $youth);
            }

            // A user with neither role matches nothing.
            if (! $user->hasAnyRole(['department-head', 'youth-pastor'])) {
                $visible->whereIn('id', []);
            }
        });
    }

    /**
     * Matches every word of the search against name, phone or member number, ignoring case.
     * Each word may match a different column, so "Emmanuel Mensah" finds Emmanuel Mensah-Boateng.
     *
     * @param  Builder<Member>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $any) use ($like): void {
                foreach (['first_name', 'middle_name', 'last_name', 'preferred_name', 'phone', 'member_number'] as $column) {
                    $any->orWhereLike($column, $like);
                }
            });
        }
    }
}
