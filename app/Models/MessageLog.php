<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Traits\BelongsToChurch;
use Database\Factories\MessageLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every outbound SMS, WhatsApp or email, with its delivery status.
 */
class MessageLog extends Model
{
    use BelongsToChurch, HasUlids, MassPrunable;

    /** @use HasFactory<MessageLogFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'channel',
        'recipient_type',
        'recipient_id',
        'recipient_phone',
        'recipient_email',
        'message_type',
        'content',
        'status',
        'provider_reference',
        'provider_response',
        'cost_units',
        'sent_at',
    ];

    /**
     * The logs old enough to delete (docs/05: 12 months rolling). This runs from the scheduler, not for one church,
     * so it deliberately looks across all churches.
     *
     * @return Builder<MessageLog>
     */
    public function prunable(): Builder
    {
        return static::query()->withoutGlobalScopes()->where('created_at', '<', now()->subMonths(config('retention.message_logs_months')));
    }

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'recipient_type' => RecipientType::class,
            'status' => MessageStatus::class,
            'provider_response' => 'array',
            'cost_units' => 'decimal:4',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
