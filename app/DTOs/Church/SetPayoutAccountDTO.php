<?php

declare(strict_types=1);

namespace App\DTOs\Church;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Http\Requests\Church\SetPayoutAccountRequest;
use App\Models\Church;
use App\Models\User;
use LogicException;

/**
 * Where the church wants its digital gifts paid out: a mobile money wallet or a bank account, as typed, not yet checked.
 */
final readonly class SetPayoutAccountDTO
{
    use ResolvesActor;

    /**
     * Holds the admin, their church and the account.
     */
    public function __construct(
        public User $actor,
        public Church $church,
        public PayoutMethod $method,
        public string $number,
        public ?MomoNetwork $network,
        public ?string $bankCode,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(SetPayoutAccountRequest $request): self
    {
        $data = $request->validated();
        $actor = self::actorFrom($request);
        $method = PayoutMethod::from((string) $data['method']);

        return new self(
            $actor,
            $actor->church ?? throw new LogicException('A staff member always belongs to a church.'),
            $method,
            (string) $data['account_number'],
            $method === PayoutMethod::Momo ? MomoNetwork::from((string) $data['network']) : null,
            $method === PayoutMethod::Bank ? (string) $data['bank_code'] : null,
        );
    }
}
