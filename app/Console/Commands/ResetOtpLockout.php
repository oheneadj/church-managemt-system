<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\OtpService;
use App\ValueObjects\PhoneNumber;
use Illuminate\Console\Command;

/**
 * Lifts the OTP lockout for one phone number (docs/16-deployment-and-ops.md runbook).
 * Confirm the number with the church admin before running it.
 */
class ResetOtpLockout extends Command
{
    protected $signature = 'otp:reset-lockout {church : The church slug} {phone : The staff phone number}';

    protected $description = 'Clear the OTP lockout and any pending code for a staff phone number';

    public function handle(OtpService $otp): int
    {
        $church = Church::query()->where('slug', $this->argument('church'))->first();
        $phone = PhoneNumber::tryFrom((string) $this->argument('phone'));

        if ($church === null || $phone === null) {
            $this->error('Unknown church or invalid phone number.');

            return self::FAILURE;
        }

        $otp->resetLockout($church, $phone);
        $this->info('Lockout cleared.');

        return self::SUCCESS;
    }
}
