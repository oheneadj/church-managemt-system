<?php

declare(strict_types=1);

use App\Enums\PledgeStatus;
use App\Models\Pledge;
use App\Services\PledgeStatusCalculator;
use Carbon\CarbonImmutable;

// D-047: paid in full is fulfilled whenever it happens; otherwise active until the target date passes, then partial
// (something paid) or lapsed (nothing paid). Pure logic, no database.

dataset('pledge days', [
    'nothing paid, before the date' => ['1000.00', '0.00', '2026-12-31', '2026-10-28', PledgeStatus::Active],
    'part paid, before the date' => ['1000.00', '400.00', '2026-12-31', '2026-10-28', PledgeStatus::Active],
    'nothing paid, on the date itself' => ['1000.00', '0.00', '2026-12-31', '2026-12-31', PledgeStatus::Active],
    'nothing paid, the day after' => ['1000.00', '0.00', '2026-12-31', '2027-01-01', PledgeStatus::Lapsed],
    'part paid, the day after' => ['1000.00', '0.01', '2026-12-31', '2027-01-01', PledgeStatus::Partial],
    'paid in full, before the date' => ['1000.00', '1000.00', '2026-12-31', '2026-10-28', PledgeStatus::Fulfilled],
    'paid in full, after the date' => ['1000.00', '1000.00', '2026-12-31', '2027-03-01', PledgeStatus::Fulfilled],
    'paid over' => ['1000.00', '1250.50', '2026-12-31', '2026-10-28', PledgeStatus::Fulfilled],
    'one pesewa short, after the date' => ['1000.00', '999.99', '2026-12-31', '2027-01-02', PledgeStatus::Partial],
]);

it('works out the status', function (string $pledged, string $paid, string $target, string $today, PledgeStatus $expected): void {
    $status = (new PledgeStatusCalculator)->for($pledged, $paid, CarbonImmutable::parse($target), CarbonImmutable::parse($today));

    expect($status)->toBe($expected);
})->with('pledge days');

it('does not change the dates it is given', function (): void {
    $target = CarbonImmutable::parse('2026-12-31 15:30:00');
    $today = CarbonImmutable::parse('2026-12-31 08:00:00');

    (new PledgeStatusCalculator)->for('100.00', '0.00', $target, $today);

    expect($target->format('H:i'))->toBe('15:30')->and($today->format('H:i'))->toBe('08:00');
});

describe('what is left and how far along', function (): void {
    it('works out the outstanding amount and the percent', function (string $pledged, string $paid, string $outstanding, int $percent): void {
        $pledge = new Pledge(['amount_pledged' => $pledged, 'amount_paid' => $paid]);

        expect($pledge->amount_outstanding)->toBe($outstanding)->and($pledge->progress_percent)->toBe($percent);
    })->with([
        'nothing paid' => ['1000.00', '0.00', '1000.00', 0],
        'a third' => ['300.00', '100.00', '200.00', 33],
        'a part of a percent is dropped' => ['1000.00', '999.99', '0.01', 99],
        'paid in full' => ['1000.00', '1000.00', '0.00', 100],
        'paid over stops at nothing left and 100%' => ['1000.00', '1500.00', '0.00', 100],
    ]);
});
