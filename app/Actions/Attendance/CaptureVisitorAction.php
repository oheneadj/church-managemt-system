<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\CaptureVisitorDTO;
use App\Enums\FollowUpStatus;
use App\Models\VisitorRecord;
use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\VisitorRecordRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records a visitor at a service (docs/02, first-time visitor capture). A visitor who gave a phone number that is already
 * known is matched to their earlier record and counted as a return visit; sending the same form twice for one service
 * counts the visit once. A visitor with no phone number is a new record every time, because there is nothing to match them by.
 */
final class CaptureVisitorAction
{
    /**
     * Takes the visitor and attendance repositories.
     */
    public function __construct(
        private readonly VisitorRecordRepositoryInterface $visitors,
        private readonly AttendanceRecordRepositoryInterface $attendance,
    ) {}

    /**
     * Records the visitor and their attendance. The returned record has `wasRecentlyCreated` set when it is new.
     */
    public function execute(CaptureVisitorDTO $dto): VisitorRecord
    {
        return DB::transaction(function () use ($dto): VisitorRecord {
            $visitor = $dto->phone === null ? null : $this->visitors->findByPhone($dto->phone->e164);

            if ($visitor === null) {
                $visitor = $this->visitors->create([
                    'church_id' => $dto->actor->church_id,
                    'name' => $dto->name,
                    'phone' => $dto->phone?->e164,
                    'how_heard' => $dto->howHeard,
                    'first_visit_service_id' => $dto->service->id,
                    'first_visit_date' => $dto->service->service_date,
                    'last_visit_date' => $dto->service->service_date,
                    'follow_up_status' => FollowUpStatus::Pending,
                ]);
                $this->attendance->insertVisitor($dto->service, $visitor, now());

                return $visitor;
            }

            // A returning visitor: count the visit only if this service has not counted them already.
            if ($this->attendance->insertVisitor($dto->service, $visitor, now())) {
                $this->visitors->recordReturn($visitor, $dto->service->service_date);
            }

            return $visitor;
        });
    }
}
