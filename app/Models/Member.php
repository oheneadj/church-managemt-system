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
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person the church knows about, from prospect to active member.
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
        return $this->belongsToMany(Department::class, 'member_departments', 'member_id', 'department_id')->withPivot('role', 'joined_at')->withTimestamps();
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
}
