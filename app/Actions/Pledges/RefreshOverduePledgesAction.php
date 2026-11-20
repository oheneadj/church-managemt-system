<?php

declare(strict_types=1);

namespace App\Actions\Pledges;

use App\Models\Pledge;
use App\Repositories\PledgeRepositoryInterface;
use App\Services\PledgeProgressService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The daily job's work: moves every active pledge whose target date has passed to partial or lapsed (D-047). Each
 * pledge is refreshed as its own church and in its own transaction, so one failure never undoes the others.
 */
final class RefreshOverduePledgesAction
{
    /**
     * Takes the pledge repository, the progress service and the tenant helper.
     */
    public function __construct(
        private readonly PledgeRepositoryInterface $pledges,
        private readonly PledgeProgressService $progress,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Refreshes every overdue active pledge and returns how many were looked at.
     */
    public function execute(): int
    {
        $count = 0;

        $this->pledges->eachActivePastDate(now(), function (Pledge $pledge) use (&$count): void {
            $church = $pledge->church ?? throw new LogicException('A pledge always belongs to a church.');

            $this->tenant->run($church, fn () => DB::transaction(fn () => $this->progress->refresh($pledge->id)));
            $count++;
        });

        return $count;
    }
}
