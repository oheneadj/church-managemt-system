<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\Http\Requests\Contributions\ListContributionsRequest;

/**
 * What the gift list is asked for: the filters and the page size. Voided gifts are left out unless is_voided is true.
 */
final readonly class ListContributionsDTO
{
    /**
     * Holds the page size and the filters.
     */
    public function __construct(
        public int $perPage,
        public ?string $memberId = null,
        public ?string $serviceId = null,
        public ?string $fundTypeId = null,
        public ?string $paymentMethod = null,
        public ?string $from = null,
        public ?string $to = null,
        public bool $isVoided = false,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListContributionsRequest $request): self
    {
        $v = $request->validated();

        return new self(
            (int) ($v['per_page'] ?? config('limits.per_page_default')),
            $v['member_id'] ?? null,
            $v['service_id'] ?? null,
            $v['fund_type_id'] ?? null,
            $v['payment_method'] ?? null,
            $v['from'] ?? null,
            $v['to'] ?? null,
            (bool) ($v['is_voided'] ?? false),
        );
    }
}
