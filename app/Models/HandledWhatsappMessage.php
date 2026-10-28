<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HandledWhatsappMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp message we have already handled, kept only so a repeat delivery from Meta is recognised and ignored.
 * Not tenant-scoped: the church is only known after the sender's phone is matched.
 *
 * @property string $id
 * @property string $message_id
 */
class HandledWhatsappMessage extends Model
{
    /** @use HasFactory<HandledWhatsappMessageFactory> */
    use HasFactory;

    use HasUlids, MassPrunable;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['message_id'];

    /**
     * The records old enough to delete. Meta stops retrying after 24 hours, so the 90 days kept for webhook logs is plenty.
     *
     * @return Builder<HandledWhatsappMessage>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(config('retention.webhook_logs_days')));
    }
}
