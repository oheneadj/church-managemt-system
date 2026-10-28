<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Contributions\StoreContributionRequest;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * A cash gift to record. `contributedAt` is when the giving happened, which may be earlier than now (Monday's entry
 * for Sunday's collection).
 */
final readonly class RecordCashContributionDTO
{
    use ResolvesActor;

    /**
     * Holds the actor and the gift's fields.
     */
    public function __construct(
        public User $actor,
        public string $fundTypeId,
        public Money $amount,
        public CarbonImmutable $contributedAt,
        public ?string $memberId = null,
        public ?string $serviceId = null,
        public ?string $notes = null,
        public ?string $pledgeId = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StoreContributionRequest $request): self
    {
        $v = $request->validated();

        return new self(
            self::actorFrom($request),
            $v['fund_type_id'],
            Money::ghs((string) $v['amount']),
            isset($v['contributed_at']) ? CarbonImmutable::parse($v['contributed_at']) : CarbonImmutable::now(),
            $v['member_id'] ?? null,
            $v['service_id'] ?? null,
            $v['notes'] ?? null,
            $v['pledge_id'] ?? null,
        );
    }
}
