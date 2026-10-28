<?php

declare(strict_types=1);

use App\Enums\FamilyRole;
use App\Exceptions\FamilyHeadAlreadyTakenException;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Repositories\ChurchRepositoryInterface;
use App\Services\MemberFamilyService;
use App\Services\MemberNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Contested slots (agreement section 10)
|--------------------------------------------------------------------------
| Two requests race for the same single slot. Request A holds the row lock inside an open transaction.
| Request B, on its own database connection, must be refused the lock while A holds it, and must see A's
| result once A commits. That gives exactly one winner.
*/

beforeEach(function (): void {
    // A second connection to the same test database stands in for the second simultaneous request.
    config(['database.connections.pgsql_b' => config('database.connections.pgsql')]);
});

afterEach(function (): void {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    DB::purge('pgsql_b');
});

/** Tries to lock a row on the second connection, waiting only 300 ms, and reports whether the lock was refused. */
function lockIsRefusedOnSecondConnection(string $model, string $id): bool
{
    try {
        DB::connection('pgsql_b')->transaction(function () use ($model, $id): void {
            DB::connection('pgsql_b')->statement("SET LOCAL lock_timeout = '300ms'");
            $model::on('pgsql_b')->withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();
        });
    } catch (QueryException $exception) {
        return str_contains($exception->getMessage(), 'lock timeout') || str_contains($exception->getMessage(), '55P03');
    }

    return false;
}

it('lets only one of two simultaneous requests take the head of a family', function (): void {
    $church = Church::factory()->create();
    app()->instance('current.church', $church);
    $family = Family::factory()->create(['church_id' => $church->id]);
    $first = Member::factory()->create(['church_id' => $church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child]);
    $second = Member::factory()->create(['church_id' => $church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child]);
    $service = app(MemberFamilyService::class);

    // Request A passes the check and holds the family lock, with its transaction still open.
    DB::beginTransaction();
    $first->family_role = FamilyRole::Head;
    $service->guard($first);

    // Request B cannot get past the same check while A holds the lock.
    expect(lockIsRefusedOnSecondConnection(Family::class, $family->id))->toBeTrue();

    // A saves the head role and commits.
    $first->save();
    $service->claimHead($first);
    DB::commit();

    // B now runs the check again, sees A's head, and is refused. One winner, one rejection.
    $second->family_role = FamilyRole::Head;
    expect(fn () => $service->guard($second))->toThrow(FamilyHeadAlreadyTakenException::class);
    expect(Member::query()->where('family_id', $family->id)->where('family_role', 'head')->count())->toBe(1);
});

it('locks the church row before the member number counter is read', function (): void {
    $church = Church::factory()->create(['member_number_prefix' => 'GRC']);

    // Request A locks the church to read the counter. The lock alone must keep B out, before any increment
    // happens, because a counter read without the lock can be stale and hand out the same number twice.
    DB::beginTransaction();
    app(ChurchRepositoryInterface::class)->lock($church->id);

    expect(lockIsRefusedOnSecondConnection(Church::class, $church->id))->toBeTrue();

    DB::rollBack();
});

it('never hands the same member number to two requests', function (): void {
    $church = Church::factory()->create(['member_number_prefix' => 'GRC']);
    $numbers = app(MemberNumberGenerator::class);

    DB::beginTransaction();
    $firstNumber = $numbers->next($church->id);
    DB::commit();

    // After the first request commits, the next one gets the next number, never the same one.
    DB::beginTransaction();
    $secondNumber = $numbers->next($church->id);
    DB::commit();

    expect($firstNumber)->toBe('GRC-0001')->and($secondNumber)->toBe('GRC-0002');
});

it('would let both through without the lock, which is why the lock is there', function (): void {
    $church = Church::factory()->create();
    $family = Family::factory()->create(['church_id' => $church->id]);

    // With no lock held, the second connection takes the row immediately.
    expect(lockIsRefusedOnSecondConnection(Family::class, $family->id))->toBeFalse();
});
