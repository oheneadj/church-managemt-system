<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastStatus;
use App\Enums\MessageChannel;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One message a staff member sent to many members. It records who sent what, by which filters, and how it went; each text it
 * produces is a `message_logs` line with this broadcast's id.
 *
 * @property string $id
 * @property string $church_id
 * @property string $sent_by
 * @property MessageChannel $channel
 * @property string $message
 * @property array<string, string>|null $filters the recipient filter, as sent
 * @property int $parts_per_message
 * @property int|null $recipient_count set once the texts are queued
 * @property BroadcastStatus $status
 * @property CarbonImmutable|null $created_at
 */
class Broadcast extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'sent_by',
        'channel',
        'message',
        'filters',
        'parts_per_message',
        'recipient_count',
        'status',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'filters' => 'array',
            'parts_per_message' => 'integer',
            'recipient_count' => 'integer',
            'status' => BroadcastStatus::class,
        ];
    }
}
