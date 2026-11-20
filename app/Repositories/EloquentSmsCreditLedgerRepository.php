<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\CreditLedgerType;
use App\Models\SmsCreditLedger;
use App\Scopes\ChurchScope;

/**
 * Eloquent implementation of the SMS credit ledger queries. The ledger is read by message, from a job that runs with no
 * church in context, so the church scope is lifted here; the message log id is a ULID that only one church owns.
 */
class EloquentSmsCreditLedgerRepository implements SmsCreditLedgerRepositoryInterface
{
    /**
     * The line of this type that points at this message log row, if there is one.
     */
    public function findFor(string $messageLogId, CreditLedgerType $type): ?SmsCreditLedger
    {
        return SmsCreditLedger::query()->withoutGlobalScope(ChurchScope::class)->where('reference_id', $messageLogId)->where('type', $type)->first();
    }

    /**
     * Adds a line to the ledger.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): SmsCreditLedger
    {
        return SmsCreditLedger::query()->create($attributes);
    }
}
