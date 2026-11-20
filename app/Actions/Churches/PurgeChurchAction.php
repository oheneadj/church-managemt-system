<?php

declare(strict_types=1);

namespace App\Actions\Churches;

use App\DTOs\Churches\PurgeChurchDTO;
use App\Services\ChurchPurgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purges a cancelled church's personal data. The database work is all or nothing. Deleting the stored files comes
 * after it and is safe to repeat, so a run that stopped half way can simply be run again.
 */
final class PurgeChurchAction
{
    /**
     * Takes the purge service.
     */
    public function __construct(private readonly ChurchPurgeService $purge) {}

    /**
     * Purges the church's data (if that has not happened yet) and deletes its files.
     */
    public function execute(PurgeChurchDTO $dto): void
    {
        $church = $dto->church;

        if ($church->purged_at === null) {
            DB::transaction(fn () => $this->purge->purgeData($church));
            Log::info('Church purged', ['church_id' => $church->id]);
        }

        $this->purge->deleteFiles($church);
    }
}
