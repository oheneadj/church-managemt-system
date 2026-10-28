<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceType;
use App\Traits\BelongsToChurch;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A church service or event that attendance is recorded against.
 */
class Service extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'type',
        'checkin_code',
        'service_date',
        'starts_at',
        'ends_at',
        'is_open',
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
