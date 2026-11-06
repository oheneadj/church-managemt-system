<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Auth\ResetStaffLockoutAction;
use App\DTOs\Auth\ResetStaffLockoutDTO;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Lifts the sign-in lockout for one staff email (docs/16-deployment-and-ops.md runbook).
 * Confirm the email with the church admin before running it.
 */
class ResetStaffLockout extends Command
{
    protected $signature = 'staff:reset-lockout {church : The church slug} {email : The staff email address}';

    protected $description = 'Clear the sign-in lockout and any pending code for a staff email';

    /**
     * Clears the lockout for one email of one church, for the support runbook.
     */
    public function handle(ChurchRepositoryInterface $churches, ResetStaffLockoutAction $action): int
    {
        $church = $churches->findBySlug((string) $this->argument('church'));
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if ($church === null || Validator::make(['email' => $email], ['email' => 'email:rfc'])->fails()) {
            $this->error('Unknown church or invalid email address.');

            return self::FAILURE;
        }

        $action->execute(new ResetStaffLockoutDTO($church, $email));
        $this->info('Lockout cleared.');

        return self::SUCCESS;
    }
}
