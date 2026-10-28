<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckInMethod;
use App\Traits\BelongsToChurch;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One member (or walk-in visitor) checked in to one service.
 */
class AttendanceRecord extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'service_id',
        'member_id',
        'visitor_name',
        'method',
        'checked_in_at',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => CheckInMethod::class,
            'checked_in_at' => 'immutable_datetime',
        ];
    }

    /**
     * The service this attendance record belongs to.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The member this attendance record belongs to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
