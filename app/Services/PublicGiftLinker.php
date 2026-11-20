<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\ServiceRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Works out what a gift given on the public page belongs to (D-073): the service being held when it was given, so the offering
 * report adds electronic gifts to the cash taken at that service, and the member whose phone it was paid from, so it counts in their
 * own giving. The member is matched only when the phone is a living member's of this church; the person approves the payment on that
 * phone, which shows they control it. Nobody is told whether a phone matched.
 */
final class PublicGiftLinker
{
    /**
     * Takes the member and service repositories.
     */
    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly ServiceRepositoryInterface $services,
    ) {}

    /**
     * The service and member a gift from this phone, given now, belongs to. Either may be empty.
     *
     * @return array{service_id: ?string, member_id: ?string}
     */
    public function linksFor(Church $church, string $phone): array
    {
        return [
            'service_id' => $this->services->servingAt(CarbonImmutable::now($church->timezone))?->id,
            'member_id' => $this->members->findLivingByPhone($phone)?->id,
        ];
    }
}
