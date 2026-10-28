<?php

declare(strict_types=1);

namespace App\DTOs\Pledges;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Pledges\StorePledgeRequest;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * A new pledge.
 */
final readonly class CreatePledgeDTO
{
    use ResolvesActor;

    /**
     * Holds the actor and the pledge's fields.
     */
    public function __construct(
        public User $actor,
        public string $memberId,
        public string $fundTypeId,
        public string $description,
        public Money $amountPledged,
        public CarbonImmutable $targetDate,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StorePledgeRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['member_id'], $v['fund_type_id'], $v['description'], Money::ghs((string) $v['amount_pledged']), CarbonImmutable::parse($v['target_date']));
    }
}
