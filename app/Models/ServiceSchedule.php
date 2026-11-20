<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\Weekday;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceScheduleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A weekly pattern ("every Sunday at 9:00") that makes the church's services ahead of time (D-050).
 *
 * @property string $id
 * @property string $church_id
 * @property string $name
 * @property ServiceType $type
 * @property Weekday $weekday
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property bool $is_active
 * @property string $created_by
 * @property CarbonImmutable|null $created_at
 */
class ServiceSchedule extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<ServiceScheduleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'type',
        'weekday',
        'starts_at',
        'ends_at',
        'is_active',
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
            'weekday' => Weekday::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The services this schedule has made.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
