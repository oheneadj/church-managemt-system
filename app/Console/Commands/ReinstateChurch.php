<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Churches\ReinstateChurchAction;
use App\DTOs\Churches\ReinstateChurchDTO;
use App\Exceptions\ChurchAlreadyPurgedException;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Support command: takes back a church's cancellation during the grace period. It cannot undo a purge.
 */
class ReinstateChurch extends Command
{
    protected $signature = 'church:reinstate {slug : The church web address}';

    protected $description = 'Take back a church cancellation before its personal data is purged';

    /**
     * Clears the cancellation, or says why it cannot.
     */
    public function handle(ChurchRepositoryInterface $churches, ReinstateChurchAction $action): int
    {
        $church = $churches->findBySlug((string) $this->argument('slug'));

        if ($church === null) {
            $this->error('No church has that web address. A purged church cannot be found by its old address.');

            return self::FAILURE;
        }

        try {
            $action->execute(new ReinstateChurchDTO($church));
        } catch (ChurchAlreadyPurgedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('The cancellation was taken back.');

        return self::SUCCESS;
    }
}
