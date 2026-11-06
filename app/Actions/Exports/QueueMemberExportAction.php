<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\DTOs\Exports\ExportMembersDTO;
use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Enums\QueueName;
use App\Jobs\ExportMembersJob;
use App\Models\Export;
use App\Repositories\ExportRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Starts an export of the member list (D-064). It records the export, which is also the trail that personal data was
 * exported and by whom, and queues the work on the `exports` queue once the record is committed. Nothing is built inside
 * the request (agreement section 3): the person is answered at once and emailed a link when the file is ready.
 */
final class QueueMemberExportAction
{
    /**
     * Takes the export repository.
     */
    public function __construct(private readonly ExportRepositoryInterface $exports) {}

    /**
     * Records the export and queues it.
     */
    public function execute(ExportMembersDTO $dto): Export
    {
        return DB::transaction(function () use ($dto): Export {
            $export = $this->exports->create([
                'church_id' => $dto->actor->church_id,
                'requested_by' => $dto->actor->id,
                'type' => ExportType::Members,
                'status' => ExportStatus::Queued,
                'filters' => $dto->filters,
            ]);

            ExportMembersJob::dispatch($export->id)->onQueue(QueueName::Exports->value)->afterCommit();

            return $export;
        });
    }
}
