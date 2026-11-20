<?php

declare(strict_types=1);

namespace App\DTOs\Messages;

use App\Enums\MemberStatus;

/**
 * Who a broadcast is for: members of one status (active unless another is asked for) and, optionally, one department. The
 * members who must not be texted (SMS turned off, deceased, no phone) are left out whatever is asked for.
 */
final readonly class BroadcastAudience
{
    /**
     * Holds the status and the department, if any.
     */
    public function __construct(public MemberStatus $status = MemberStatus::Active, public ?string $departmentId = null) {}

    /**
     * Builds the audience from the request's `recipient_filter` (or nothing, meaning all active members).
     *
     * @param  array<string, string>  $filter
     */
    public static function fromFilter(array $filter): self
    {
        return new self(isset($filter['status']) ? MemberStatus::from($filter['status']) : MemberStatus::Active, $filter['department_id'] ?? null);
    }
}
