<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Incident command: stops every text message from going out, without losing any. Queued messages wait and are sent
 * after `sms:resume` (up to the retry window in config/resilience.php). Use it when the SMS provider is misbehaving
 * or messages are going wrong. See docs/runbooks/sms-provider-down.md.
 */
class PauseSms extends Command
{
    protected $signature = 'sms:pause';

    protected $description = 'Hold back every text message until sms:resume is run';

    /**
     * Pauses sending.
     */
    public function handle(CircuitBreaker $breaker): int
    {
        $breaker->pause('sms');
        $this->warn('Text messages are paused. Run sms:resume to send the held ones.');

        return self::SUCCESS;
    }
}
