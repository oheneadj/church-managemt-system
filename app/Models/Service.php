<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceType;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A church service or event that attendance is recorded against.
 *
 * @property string $id
 * @property string $church_id
 * @property string|null $service_schedule_id the schedule that made this service, if one did
 * @property string $name
 * @property ServiceType $type
 * @property string|null $checkin_code
 * @property CarbonImmutable $service_date
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property bool $is_open
 * @property CarbonImmutable|null $checkin_opened_at
 * @property int|null $headcount_override
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 */
class Service extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'service_schedule_id',
        'name',
        'type',
        'checkin_code',
        'service_date',
        'starts_at',
        'ends_at',
        'is_open',
        'checkin_opened_at',
        'headcount_override',
        'notes',
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
            'type' => ServiceType::class,
            'service_date' => 'immutable_date',
            'is_open' => 'boolean',
            'checkin_opened_at' => 'immutable_datetime',
            'headcount_override' => 'integer',
        ];
    }

    /**
     * The attendance record records of this service.
     *
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * The gifts given at this service.
     *
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
