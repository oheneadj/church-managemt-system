<?php

declare(strict_types=1);

use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\ContributionRepositoryInterface;
use App\Services\MemberSummaries;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-28 10:00:00');
    $this->contributions = Mockery::mock(ContributionRepositoryInterface::class);
    $this->attendance = Mockery::mock(AttendanceRecordRepositoryInterface::class);
    $this->summaries = new MemberSummaries($this->contributions, $this->attendance);
});

it('totals giving for the year, the month and all time as exact decimal strings', function (): void {
    $this->contributions->shouldReceive('totalForMember')->with('01memberunit', Mockery::on(fn ($d) => $d?->toDateString() === '2026-01-01'))->andReturn('2400.00');
    $this->contributions->shouldReceive('totalForMember')->with('01memberunit', Mockery::on(fn ($d) => $d?->toDateString() === '2026-10-01'))->andReturn('200.00');
    $this->contributions->shouldReceive('totalForMember')->with('01memberunit', null)->andReturn('8650.00');

    $giving = $this->summaries->giving(unitMember());

    expect($giving->thisYear)->toBe('2400.00')->and($giving->thisMonth)->toBe('200.00')->and($giving->allTime)->toBe('8650.00');
});

it('counts attendance over the last 4, 8 and 12 weeks', function (): void {
    foreach ([4 => 3, 8 => 7, 12 => 10] as $weeks => $count) {
        $this->attendance->shouldReceive('countForMemberSince')->with('01memberunit', Mockery::on(fn ($d) => $d?->toDateString() === now()->subWeeks($weeks)->toDateString()))->andReturn($count);
    }

    expect($this->summaries->attendance(unitMember()))->toBe(['last_4_weeks' => 3, 'last_8_weeks' => 7, 'last_12_weeks' => 10]);
});
