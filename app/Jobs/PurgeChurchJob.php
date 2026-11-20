<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Churches\PurgeChurchAction;
use App\DTOs\Churches\PurgeChurchDTO;
use App\Models\Church;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Purges one cancelled church on a worker. A purge can touch thousands of rows and files, so it never runs in a request.
 * It is unique per church, so a slow run is never started twice, and it is safe to repeat.
 */
class PurgeChurchJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /**
     * Takes the id of the church to purge.
     */
    public function __construct(public readonly string $churchId)
    {
        $this->onQueue('scheduled');
    }

    /**
     * Makes the job unique for each church.
     */
    public function uniqueId(): string
    {
        return $this->churchId;
    }

    /**
     * Finds the church and purges it. A church that is gone already (purged and deleted) is skipped.
     */
    public function handle(PurgeChurchAction $action): void
    {
        $church = Church::query()->withTrashed()->find($this->churchId);

        if ($church !== null) {
            $action->execute(new PurgeChurchDTO($church));
        }
    }

    /**
     * Records the failure loudly. A church's personal data staying past its date is a privacy problem that needs a person.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Church purge failed', ['church_id' => $this->churchId, 'error' => $exception::class]);
    }
}
