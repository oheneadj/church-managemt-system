<?php

declare(strict_types=1);

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\ListSmsPackagesRequest;

/**
 * What the SMS credit package list is asked for: the page size.
 */
final readonly class ListSmsPackagesDTO
{
    /**
     * Holds the page size.
     */
    public function __construct(public int $perPage) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListSmsPackagesRequest $request): self
    {
        return new self((int) ($request->validated()['per_page'] ?? config('limits.per_page_default')));
    }
}
