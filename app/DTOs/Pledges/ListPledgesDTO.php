<?php

declare(strict_types=1);

namespace App\DTOs\Pledges;

use App\Http\Requests\Pledges\ListPledgesRequest;

/**
 * What the pledge list is asked for: the filters and the page size.
 */
final readonly class ListPledgesDTO
{
    /**
     * Holds the page size and the filters.
     */
    public function __construct(public int $perPage, public ?string $memberId = null, public ?string $status = null, public ?string $fundTypeId = null) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListPledgesRequest $request): self
    {
        $v = $request->validated();

        return new self((int) ($v['per_page'] ?? config('limits.per_page_default')), $v['member_id'] ?? null, $v['status'] ?? null, $v['fund_type_id'] ?? null);
    }
}
