<?php

declare(strict_types=1);

use App\Enums\Weekday;
use Carbon\CarbonImmutable;

// The days of a service schedule. The only place text becomes Carbon's day number.

it('maps every day to the number Carbon uses', function (Weekday $day): void {
    // 2026-11-01 is a Sunday, so counting forward from it gives each day in turn.
    $date = CarbonImmutable::parse('2026-11-01')->addDays($day->carbonDay());

    expect($date->dayOfWeek)->toBe($day->carbonDay())->and(strtolower($date->englishDayOfWeek))->toBe($day->value);
})->with(Weekday::cases());

it('has seven days, Sunday first, with plain labels', function (): void {
    expect(Weekday::cases())->toHaveCount(7)->and(Weekday::Sunday->carbonDay())->toBe(0)->and(Weekday::Saturday->carbonDay())->toBe(6)
        ->and(Weekday::Wednesday->label())->toBe('Wednesday');
});
