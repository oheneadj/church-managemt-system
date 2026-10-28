<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Incident command: stops every WhatsApp answer from going out, without losing any and without stopping check-ins
 * (members are still recorded; only the reply waits). Held answers are sent after `whatsapp:resume`, up to the retry
 * window in config/whatsapp.php. Use it when Meta is misbehaving or answers are going wrong.
 */
class PauseWhatsApp extends Command
{
    protected $signature = 'whatsapp:pause';

    protected $description = 'Hold back every WhatsApp answer until whatsapp:resume is run';

    /**
     * Pauses sending.
     */
    public function handle(CircuitBreaker $breaker): int
    {
        $breaker->pause(config('whatsapp.circuit'));
        $this->warn('WhatsApp answers are paused. Check-ins are still recorded. Run whatsapp:resume to send the held ones.');

        return self::SUCCESS;
    }
}
