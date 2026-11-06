<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CSV export a staff member asked for. The row is the record that personal data was exported, by whom and when; the file
 * is private and is removed after a few days (`config/exports.php`).
 *
 * @property string $id
 * @property string $church_id
 * @property string $requested_by
 * @property ExportType $type
 * @property ExportStatus $status
 * @property array<string, string>|null $filters the filters the staff member chose, as sent
 * @property int|null $row_count
 * @property string|null $file_path the private storage path, not a link
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $file_deleted_at
 * @property CarbonImmutable|null $created_at
 */
class Export extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<ExportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'requested_by',
        'type',
        'status',
        'filters',
        'row_count',
        'file_path',
        'completed_at',
        'file_deleted_at',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExportType::class,
            'status' => ExportStatus::class,
            'filters' => 'array',
            'completed_at' => 'immutable_datetime',
            'file_deleted_at' => 'immutable_datetime',
        ];
    }
}
