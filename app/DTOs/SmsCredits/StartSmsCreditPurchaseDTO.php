<?php

declare(strict_types=1);

namespace App\DTOs\SmsCredits;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\MomoNetwork;
use App\Http\Requests\SmsCredits\StartSmsCreditPurchaseRequest;
use App\Models\Church;
use App\Models\User;
use LogicException;

/**
 * The start of a purchase of SMS credits: who buys it, for which church, the package, and the MoMo number and network to charge.
 */
final readonly class StartSmsCreditPurchaseDTO
{
    use ResolvesActor;

    /**
     * Holds the buyer, their church, the package id and the payer's number and network.
     */
    public function __construct(
        public User $actor,
        public Church $church,
        public string $packageId,
        public string $phone,
        public MomoNetwork $network,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StartSmsCreditPurchaseRequest $request): self
    {
        $v = $request->validated();
        $actor = self::actorFrom($request);

        return new self($actor, $actor->church ?? throw new LogicException('A staff member always belongs to a church.'), $v['sms_package_id'], $v['phone'], MomoNetwork::from($v['network']));
    }
}
