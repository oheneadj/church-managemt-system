<?php

declare(strict_types=1);

use App\Enums\CheckInMethod;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use App\Repositories\AttendanceRecordRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Two check-ins at once (agreement sections 10 and 11, layer 4)
|--------------------------------------------------------------------------
| The same member is checked in twice at the same moment, from two connections. Request A has written its row inside
| a transaction that is still open. Request B must not slip a second row in: it has to wait for A, and when A commits
| B's insert is ignored. The unique index in the database is what guarantees one row.
*/

beforeEach(function (): void {
    config(['database.connections.pgsql_b' => config('database.connections.pgsql')]);
});

afterEach(function (): void {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    DB::purge('pgsql_b');
});

/** The row a check-in writes, for the second connection's plain insert. */
function checkInRow(Service $service, Member $member): array
{
    return [
        'id' => (string) Str::ulid(), 'church_id' => $service->church_id, 'service_id' => $service->id, 'member_id' => $member->id,
        'method' => CheckInMethod::Whatsapp->value, 'checked_in_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ];
}

it('leaves one row when two requests check the same member in together', function (): void {
    $church = Church::factory()->create();
    app()->instance('current.church', $church);
    $service = Service::factory()->open()->create(['church_id' => $church->id]);
    $member = Member::factory()->create(['church_id' => $church->id]);

    // Request A: writes its row and has not committed yet.
    DB::beginTransaction();
    $added = app(AttendanceRecordRepositoryInterface::class)->insertMembers($service, [$member->id], CheckInMethod::Manual, now());
    expect($added)->toBe(1);

    // Request B, on its own connection, has to wait for A: with a short wait it times out instead of inserting a second row.
    $blocked = false;
    try {
        DB::connection('pgsql_b')->transaction(function () use ($service, $member): void {
            DB::connection('pgsql_b')->statement("SET LOCAL lock_timeout = '300ms'");
            DB::connection('pgsql_b')->table('attendance_records')->insertOrIgnore([checkInRow($service, $member)]);
        });
    } catch (QueryException $exception) {
        $blocked = str_contains($exception->getMessage(), '55P03') || str_contains($exception->getMessage(), 'lock timeout');
    }
    expect($blocked)->toBeTrue();

    // A commits. Now B's insert goes through the same statement and is ignored.
    DB::commit();
    $ignored = DB::connection('pgsql_b')->table('attendance_records')->insertOrIgnore([checkInRow($service, $member)]);

    expect($ignored)->toBe(0)
        ->and(AttendanceRecord::query()->withoutGlobalScopes()->where('service_id', $service->id)->where('member_id', $member->id)->count())->toBe(1);
});
