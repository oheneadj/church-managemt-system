<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\MomoNetwork;
use App\Http\Requests\Contributions\InitiateContributionRequest;
use App\Models\Church;
use App\Models\User;
use App\ValueObjects\Money;
use LogicException;

/**
 * A mobile money gift to start: who gives what, at which service or pledge, and the wallet to ask for the money.
 */
final readonly class InitiateContributionDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, their church and the gift's fields.
     */
    public function __construct(
        public User $actor,
        public Church $church,
        public string $fundTypeId,
        public Money $amount,
        public string $phone,
        public MomoNetwork $network,
        public ?string $memberId = null,
        public ?string $serviceId = null,
        public ?string $pledgeId = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(InitiateContributionRequest $request): self
    {
        $v = $request->validated();
        $actor = self::actorFrom($request);

        return new self(
            $actor,
            $actor->church ?? throw new LogicException('A staff member always belongs to a church.'),
            $v['fund_type_id'],
            Money::ghs((string) $v['amount']),
            $v['phone'],
            MomoNetwork::from((string) $v['network']),
            $v['member_id'] ?? null,
            $v['service_id'] ?? null,
            $v['pledge_id'] ?? null,
        );
    }
}
