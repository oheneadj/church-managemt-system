<?php

declare(strict_types=1);

use App\DTOs\Attendance\AttendanceSummary;
use App\DTOs\Attendance\MemberAttendance;

// Rates are whole percents worked out with integers (agreement section 12 spirit: no floats for counts that get compared).

it('works out the attendance rate with rounding to the nearest percent', function (int $members, int $active, int $rate): void {
    expect((new AttendanceSummary($members, $members, 0, AttendanceSummary::emptyByMethod(), $active))->attendanceRate())->toBe($rate);
})->with([[0, 10, 0], [1, 3, 33], [2, 3, 67], [3, 3, 100], [5, 8, 63], [4, 0, 0]]);

it('starts every check-in method at zero', function (): void {
    expect(AttendanceSummary::emptyByMethod())->toBe(['manual' => 0, 'whatsapp' => 0, 'qr' => 0, 'biometric' => 0]);
});

it('gives a member a zero rate when no service was held', function (): void {
    expect((new MemberAttendance(unitMember(), 12, 0, collect()))->rate())->toBe(0);
});
