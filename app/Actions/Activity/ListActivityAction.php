<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\DTOs\Activity\ActivityItem;
use App\DTOs\Activity\ListActivityDTO;
use App\Enums\ActivityKind;
use App\Models\AuditLog;
use App\Repositories\AuditLogRepositoryInterface;
use App\Services\ActivityDescriber;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the recent activity feed (D-068): the change history, newest first, of only the kinds of line the person
 * may see, each as a plain sentence. The page and its total count only what they may see, so the total gives nothing away.
 */
final class ListActivityAction
{
    /**
     * Takes the history repository and the service that writes the sentences.
     */
    public function __construct(
        private readonly AuditLogRepositoryInterface $history,
        private readonly ActivityDescriber $describer,
    ) {}

    /**
     * Lists one page of activity.
     *
     * @return LengthAwarePaginator<int, ActivityItem>
     */
    public function execute(ListActivityDTO $dto): LengthAwarePaginator
    {
        $page = $this->history->paginateActivity(ActivityKind::availableTo($dto->viewer), $dto->perPage);

        return $page->through(function (AuditLog $line): ActivityItem {
            // The query chose only kinds the feed knows, so every line has one.
            $kind = $this->describer->kindOf($line) ?? throw new \LogicException('A line in the activity feed always has a kind.');

            return $this->describer->describe($line, $kind);
        });
    }
}
