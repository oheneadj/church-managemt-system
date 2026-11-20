<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Churches\CancelChurchAction;
use App\DTOs\Churches\CancelChurchDTO;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Support command: cancels a church by its slug. The church keeps working for the grace period (config/retention.php),
 * then a daily job purges its personal data. It is how a cancellation is made until the settings page exists.
 */
class CancelChurch extends Command
{
    protected $signature = 'church:cancel {slug : The church web address, for example grace-assembly}';

    protected $description = 'Cancel a church and start the grace period before its personal data is purged';

    /**
     * Cancels the church and says when its data will be purged.
     */
    public function handle(ChurchRepositoryInterface $churches, CancelChurchAction $action): int
    {
        $church = $churches->findBySlug((string) $this->argument('slug'));

        if ($church === null) {
            $this->error('No church has that web address.');

            return self::FAILURE;
        }

        $action->execute(new CancelChurchDTO($church));
        $this->info('Cancelled. Its personal data will be purged on '.$church->cancelled_at?->addDays(config('retention.cancelled_church_grace_days'))->toDateString().'.');

        return self::SUCCESS;
    }
}
