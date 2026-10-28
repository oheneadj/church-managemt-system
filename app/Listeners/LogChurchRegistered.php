<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChurchRegistered;
use Illuminate\Support\Facades\Log;

/**
 * Writes the "church onboarded" line that docs/17 asks for. It logs ids only, never names or phone numbers.
 * It is not queued: writing one log line is cheaper than queueing it.
 */
class LogChurchRegistered
{
    /**
     * Logs that a church started.
     */
    public function handle(ChurchRegistered $event): void
    {
        Log::info('Church registered', ['church_id' => $event->church->id, 'admin_id' => $event->admin->id]);
    }
}
