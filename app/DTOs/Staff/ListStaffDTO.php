<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use App\Http\Requests\Staff\ListStaffRequest;

/**
 * What the staff list is asked for: the page size.
 */
final readonly class ListStaffDTO
{
    /**
     * Holds the page size.
     */
    public function __construct(public int $perPage) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListStaffRequest $request): self
    {
        return new self((int) ($request->validated()['per_page'] ?? config('limits.per_page_default')));
    }
}
