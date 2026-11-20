<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChurchCancelled;
use Illuminate\Support\Facades\Log;

/**
 * Writes the "church cancelled" line, with the date its data will be purged. Ids and dates only, no names.
 */
class LogChurchCancelled
{
    /**
     * Logs that a church cancelled.
     */
    public function handle(ChurchCancelled $event): void
    {
        Log::info('Church cancelled', [
            'church_id' => $event->church->id,
            'purge_after' => $event->church->cancelled_at?->addDays(config('retention.cancelled_church_grace_days'))->toDateString(),
        ]);
    }
}
