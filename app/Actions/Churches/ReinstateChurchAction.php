<?php

declare(strict_types=1);

namespace App\Actions\Churches;

use App\DTOs\Churches\ReinstateChurchDTO;
use App\Exceptions\ChurchAlreadyPurgedException;
use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Takes back a cancellation during the grace period. Once the data is purged it cannot be undone.
 */
final class ReinstateChurchAction
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Clears the cancellation date.
     *
     * @throws ChurchAlreadyPurgedException
     */
    public function execute(ReinstateChurchDTO $dto): Church
    {
        return DB::transaction(function () use ($dto): Church {
            $church = $dto->church;

            if ($church->purged_at !== null) {
                throw new ChurchAlreadyPurgedException;
            }

            $church->cancelled_at = null;

            return $this->churches->save($church);
        });
    }
}
