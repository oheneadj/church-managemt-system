<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\MessageLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every outbound SMS, WhatsApp or email, with its delivery status.
 *
 * @property string $id
 * @property string|null $church_id null only for a platform message sent before a church exists (the sign-up code)
 * @property string|null $broadcast_id the broadcast this text belongs to, if it was sent as part of one
 * @property MessageChannel $channel
 * @property RecipientType $recipient_type
 * @property string|null $recipient_id
 * @property string|null $recipient_phone
 * @property string|null $recipient_email
 * @property string $message_type
 * @property string $content what was sent, with any secret (a code) left out
 * @property MessageStatus $status
 * @property string|null $provider_reference
 * @property array<string, mixed>|null $provider_response
 * @property string|null $cost_units
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 */
class MessageLog extends Model
{
    use BelongsToChurch, HasUlids, MassPrunable;

    /** @use HasFactory<MessageLogFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'broadcast_id',
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
        return self::query()->withoutGlobalScopes()->where('created_at', '<', now()->subMonths(config('retention.message_logs_months')));
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
