<?php

declare(strict_types=1);

namespace App\Actions\Churches;

use App\DTOs\Churches\CancelChurchDTO;
use App\Events\ChurchCancelled;
use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a church. It keeps working, so its admin can export their data, until the grace period ends and a daily
 * job purges the personal data. Cancelling twice changes nothing: the first date stands.
 */
final class CancelChurchAction
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Starts the grace period and announces it.
     */
    public function execute(CancelChurchDTO $dto): Church
    {
        return DB::transaction(function () use ($dto): Church {
            $church = $dto->church;

            if ($church->cancelled_at === null) {
                $church->cancelled_at = now()->toImmutable();
                $this->churches->save($church);
                ChurchCancelled::dispatch($church);
            }

            return $church;
        });
    }
}
