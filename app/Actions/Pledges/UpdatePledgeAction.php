<?php

declare(strict_types=1);

namespace App\Actions\Pledges;

use App\DTOs\Pledges\UpdatePledgeDTO;
use App\Enums\AuditEvent;
use App\Models\Pledge;
use App\Repositories\PledgeRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\PledgeProgressService;
use Illuminate\Support\Facades\DB;

/**
 * Changes a pledge's description, amount or target date. The amount and date decide its status, so the paid amount
 * and status are recalculated in the same transaction, with the row locked, and the change is written to the history.
 */
final class UpdatePledgeAction
{
    /**
     * Takes the pledge repository, the progress service and the change-history writer.
     */
    public function __construct(
        private readonly PledgeRepositoryInterface $pledges,
        private readonly PledgeProgressService $progress,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Changes the pledge as the staff member in the DTO.
     */
    public function execute(UpdatePledgeDTO $dto): Pledge
    {
        return DB::transaction(function () use ($dto): Pledge {
            $pledge = $this->pledges->lockForUpdate($dto->pledge->id);
            $old = $pledge->only(array_keys($dto->attributes));

            $this->pledges->update($pledge, $dto->attributes);
            $refreshed = $this->progress->refresh($pledge->id);

            $this->audit->record($refreshed, AuditEvent::Updated, $dto->actor, $this->plain($old), $this->plain($refreshed->only(array_keys($dto->attributes))));

            return $this->pledges->withDetail($refreshed, false);
        });
    }

    /**
     * The values as plain text and numbers, so dates are written to the history as dates.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn (mixed $value): mixed => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value, $values);
    }
}
