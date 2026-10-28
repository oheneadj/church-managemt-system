<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Enums\StaffRole;
use App\Traits\BelongsToChurch;
use App\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property-read string $full_name
 * @property-read string|null $last_attended_at the latest check-in time, present only on the absent-members query
 * @property string|null $preferred_name
 * @property Gender $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property string $phone
 * @property string|null $address
 * @property string|null $photo_url the storage path of the photo, not a link; the API signs it on the way out
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
    /** The columns the directory search looks in. */
    private const SEARCHABLE_COLUMNS = ['first_name', 'middle_name', 'last_name', 'preferred_name', 'phone', 'member_number'];

    /** The roles that see every member of the church. */
    private const SEE_EVERYONE = [StaffRole::ChurchAdmin, StaffRole::Pastor, StaffRole::FinanceSecretary];

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
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
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
     * The member's first, middle and last name in one string, as the screens show it.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '));
    }

    /**
     * The family this member belongs to.
     *
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * The user this member belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedPastor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pastor_id');
    }

    /**
     * The department records this member is linked to.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'member_departments', 'member_id', 'department_id')->withPivot('role', 'joined_at')->withTimestamps()->wherePivotNull('deleted_at');
    }

    /**
     * The member milestone records of this member.
     *
     * @return HasMany<MemberMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(MemberMilestone::class);
    }

    /**
     * The attendance record records of this member.
     *
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * The contribution records of this member.
     *
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
        if ($user->hasAnyRole(self::SEE_EVERYONE)) {
            return;
        }

        $query->where(function (Builder $visible) use ($user): void {
            // Start from nobody, then open up what each of the user's roles may see.
            $visible->whereIn('id', []);

            if ($user->hasRole(StaffRole::DepartmentHead)) {
                $visible->orWhereHas('departments', fn (Builder $department) => $department->where('departments.head_user_id', $user->getKey()));
            }

            if ($user->hasRole(StaffRole::YouthPastor)) {
                $this->addYouthBranch($visible, app('current.church'));
            }
        });
    }

    /**
     * Adds members who count as youth, as an OR branch of the surrounding group (D-031). With a birth date,
     * youth means younger than the church's age limit. With no birth date, the educational level decides:
     * any level except graduate.
     *
     * @param  Builder<Member>  $query
     */
    private function addYouthBranch(Builder $query, Church $church): void
    {
        $levels = array_values(array_map(
            fn (EducationalLevel $level) => $level->value,
            array_filter(EducationalLevel::cases(), fn (EducationalLevel $level) => $level->isYouth()),
        ));

        $query->orWhere(function (Builder $youth) use ($church, $levels): void {
            $youth->where('date_of_birth', '>', today()->subYears($church->youthAgeLimit()))
                ->orWhere(fn (Builder $undated) => $undated->whereNull('date_of_birth')->whereIn('educational_level', $levels));
        });
    }

    /**
     * Matches every word of the search against name, phone or member number, ignoring case.
     * Each word may match a different column, so "Emmanuel Mensah" finds Emmanuel Mensah-Boateng.
     * A word that is a whole phone number in any common form (0201234567, 233201234567) also finds
     * the number stored in international form (docs/qa QA-04, TC-04-08).
     *
     * @param  Builder<Member>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $phone = PhoneNumber::tryFrom($word);

            $query->where(function (Builder $any) use ($like, $phone): void {
                foreach (self::SEARCHABLE_COLUMNS as $column) {
                    $any->orWhereLike($column, $like);
                }

                if ($phone !== null) {
                    $any->orWhere('phone', $phone->e164);
                }
            });
        }
    }

    /**
     * The links between this member and their departments, including the role in each.
     *
     * @return HasMany<MemberDepartment, $this>
     */
    public function departmentLinks(): HasMany
    {
        return $this->hasMany(MemberDepartment::class);
    }

    /**
     * The family this member is the head of, if any.
     *
     * @return HasOne<Family, $this>
     */
    public function headedFamily(): HasOne
    {
        return $this->hasOne(Family::class, 'head_member_id');
    }

    /**
     * The agreements this member has made to receive messages, including withdrawn ones.
     *
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * The pledges this member has made.
     *
     * @return HasMany<Pledge, $this>
     */
    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    /**
     * The change history of this member.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
