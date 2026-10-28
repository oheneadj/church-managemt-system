<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\EnvironmentCheck;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

/**
 * Fails when a deployed environment is misconfigured. The container entrypoint and the deploy
 * script run it first, so a bad release stops at start-up instead of failing on the first request.
 */
class CheckEnvironment extends Command
{
    protected $signature = 'env:check';

    protected $description = 'Fail loudly if required production settings are missing or unsafe';

    public function handle(Repository $config): int
    {
        $problems = EnvironmentCheck::problems($config);

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems === []) {
            $this->info('Environment looks good.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
