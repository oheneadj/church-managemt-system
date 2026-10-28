<?php

declare(strict_types=1);

namespace App\DTOs\FundTypes;

use App\Http\Requests\FundTypes\ListFundTypesRequest;

/**
 * What the fund type list is asked for: an optional active filter and the page size.
 */
final readonly class ListFundTypesDTO
{
    /**
     * Holds the page size and the active filter.
     */
    public function __construct(public int $perPage, public ?bool $isActive = null) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListFundTypesRequest $request): self
    {
        $v = $request->validated();

        return new self((int) ($v['per_page'] ?? config('limits.per_page_default')), isset($v['is_active']) ? (bool) $v['is_active'] : null);
    }
}
