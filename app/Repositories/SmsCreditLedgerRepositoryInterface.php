<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\CreditLedgerType;
use App\Models\SmsCreditLedger;

/**
 * Every query on the SMS credit ledger lives behind this contract.
 */
interface SmsCreditLedgerRepositoryInterface
{
    /**
     * The line of this type that points at this message log row, if there is one.
     */
    public function findFor(string $messageLogId, CreditLedgerType $type): ?SmsCreditLedger;

    /**
     * Adds a line to the ledger. Lines are never changed or removed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): SmsCreditLedger;
}
