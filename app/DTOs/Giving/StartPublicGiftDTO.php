<?php

declare(strict_types=1);

namespace App\DTOs\Giving;

use App\Enums\MomoNetwork;
use App\Http\Requests\Giving\StartPublicGiftRequest;
use App\Models\Church;
use App\ValueObjects\Money;

/**
 * A gift a person starts on the public giving page: what and how much, the wallet to ask, and an optional name. The church is the
 * one the address names. `trapFilled` is true when a bot filled in the hidden field.
 */
final readonly class StartPublicGiftDTO
{
    /**
     * Holds the church and the gift's fields.
     */
    public function __construct(
        public Church $church,
        public string $fundTypeId,
        public Money $amount,
        public string $phone,
        public MomoNetwork $network,
        public ?string $giverName,
        public bool $trapFilled,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StartPublicGiftRequest $request): self
    {
        $v = $request->validated();

        return new self(
            app('current.church'),
            $v['fund_type_id'],
            Money::ghs((string) $v['amount']),
            $v['phone'],
            MomoNetwork::from((string) $v['network']),
            isset($v['giver_name']) && trim((string) $v['giver_name']) !== '' ? trim((string) $v['giver_name']) : null,
            $request->trapFilled(),
        );
    }
}
