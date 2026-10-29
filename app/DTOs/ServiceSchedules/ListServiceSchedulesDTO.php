<?php

declare(strict_types=1);

namespace App\DTOs\ServiceSchedules;

use App\Http\Requests\ServiceSchedules\ListServiceSchedulesRequest;

/**
 * What the schedule list is asked for: an optional on/off filter and the page size.
 */
final readonly class ListServiceSchedulesDTO
{
    /**
     * Holds the page size and the filter.
     */
    public function __construct(public int $perPage, public ?bool $isActive = null) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListServiceSchedulesRequest $request): self
    {
        $v = $request->validated();

        return new self((int) ($v['per_page'] ?? config('limits.per_page_default')), isset($v['is_active']) ? (bool) $v['is_active'] : null);
    }
}
