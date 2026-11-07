<?php

declare(strict_types=1);

namespace App\DTOs\Activity;

use App\Enums\ActivityKind;
use Carbon\CarbonImmutable;

/**
 * One line of the activity feed, ready to show: what kind it is, the plain sentence, who did it and when. It carries no old or
 * new values, no reason and no contact details, only what the sentence says.
 */
final readonly class ActivityItem
{
    /**
     * Holds the finished line.
     */
    public function __construct(
        public string $id,
        public ActivityKind $kind,
        public string $text,
        public ?string $actorName,
        public ?CarbonImmutable $occurredAt,
    ) {}
}
