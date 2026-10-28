<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Lets WhatsApp answers go out again after `whatsapp:pause`, and clears the failure history so the circuit starts closed.
 */
class ResumeWhatsApp extends Command
{
    protected $signature = 'whatsapp:resume';

    protected $description = 'Send WhatsApp answers again after whatsapp:pause';

    /**
     * Resumes sending.
     */
    public function handle(CircuitBreaker $breaker): int
    {
        $breaker->resume(config('whatsapp.circuit'));
        $this->info('WhatsApp answers are going out again.');

        return self::SUCCESS;
    }
}
