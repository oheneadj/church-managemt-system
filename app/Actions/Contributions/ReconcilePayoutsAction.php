<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\Services\PayoutReconciler;
use Illuminate\Support\Facades\Log;

/**
 * The daily check that what was collected has been paid out (D-018, D-069 step e5). It asks the reconciler what it found and writes it to the
 * log: a critical line, with the number found and the first few ids of each kind, when anything is wrong, so the alert reaches a person; an
 * info line when all is well, so a quiet log means the check ran. Only ids and counts are logged, never an amount, a name or a phone.
 */
final class ReconcilePayoutsAction
{
    /**
     * Takes the reconciler.
     */
    public function __construct(private readonly PayoutReconciler $reconciler) {}

    /**
     * Runs the check, logs what it found, and returns the ids by kind (empty when all is well).
     *
     * @return array<string, array<int, string>>
     */
    public function execute(): array
    {
        $findings = $this->reconciler->findings();

        if ($findings === []) {
            Log::info('Payout reconciliation found nothing wrong');

            return [];
        }

        $limit = (int) config('giving.reconcile_ids_logged');

        Log::critical('Payout reconciliation found problems', [
            'counts' => array_map('count', $findings),
            'ids' => array_map(fn (array $ids): array => array_slice($ids, 0, $limit), $findings),
        ]);

        return $findings;
    }
}
