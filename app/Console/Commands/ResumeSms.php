<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Incident command: lets text messages go out again after sms:pause, and clears an open circuit.
 */
class ResumeSms extends Command
{
    protected $signature = 'sms:resume';

    protected $description = 'Send held text messages again after sms:pause or an open circuit';

    /**
     * Resumes sending.
     */
    public function handle(CircuitBreaker $breaker): int
    {
        $breaker->resume('sms');
        $this->info('Text messages are flowing again.');

        return self::SUCCESS;
    }
}
