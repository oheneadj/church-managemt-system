<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of change history. Append-only: it is written once, never updated and never deleted, so it
 * has no updated_at and no soft deletes (the one deliberate exception to the soft-delete rule).
 *
 * @property string $id
 * @property AuditEvent $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property list<string>|null $changed_fields
 * @property string|null $actor_name
 * @property string|null $reason
 * @property CarbonImmutable|null $created_at
 */
class AuditLog extends Model
{
    use BelongsToChurch, HasUlids;

    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'changed_fields',
        'actor_id',
        'actor_name',
        'request_id',
        'reason',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
        ];
    }

    /**
     * The record this entry is about.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
