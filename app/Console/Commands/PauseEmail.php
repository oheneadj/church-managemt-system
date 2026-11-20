<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Incident command: stops every email from going out, without losing any. Queued emails wait and are sent after
 * `email:resume` (up to the retry window in config/resilience.php). Use it when the mail provider is misbehaving or emails
 * are going wrong. See docs/runbooks/email-provider-down.md.
 */
class PauseEmail extends Command
{
    protected $signature = 'email:pause';

    protected $description = 'Hold back every email until email:resume is run';

    /**
     * Pauses sending.
     */
    public function handle(CircuitBreaker $breaker): int
    {
        $breaker->pause('email');
        $this->warn('Email is paused. Run email:resume to send the held ones.');

        return self::SUCCESS;
    }
}
