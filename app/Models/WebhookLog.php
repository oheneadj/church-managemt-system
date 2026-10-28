<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookSource;
use Database\Factories\WebhookLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Raw incoming webhook payloads, kept encrypted so no event is ever lost. Not tenant-scoped: the church is only known after processing.
 */
class WebhookLog extends Model
{
    /** @use HasFactory<WebhookLogFactory> */
    use HasFactory;

    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'source',
        'event',
        'payload',
        'processed',
        'processed_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => WebhookSource::class,
            'payload' => 'encrypted',
            'processed' => 'boolean',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
