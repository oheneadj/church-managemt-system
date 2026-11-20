<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Incident command: lets email go out again after email:pause, and clears an open circuit.
 */
class ResumeEmail extends Command
{
    protected $signature = 'email:resume';

    protected $description = 'Send held emails again after email:pause or an open circuit';

    /**
     * Resumes sending.
     */
    public function handle(CircuitBreaker $breaker): int
    {
        $breaker->resume('email');
        $this->info('Email is flowing again.');

        return self::SUCCESS;
    }
}
