<?php

declare(strict_types=1);

use App\Enums\PledgeStatus;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// A gift names its pledge, and the pledge's paid amount and status follow the gifts (D-047, QA-06 TC-06-15 and
// TC-06-16): recalculated from the gifts that are not voided, in the same transaction as the gift or the void.

const PLEDGE_GIFTS = 'http://grace.localhost/api/v1/contributions';

/** Records a cash gift to the test pledge. */
function giftToPledge(array $body = []): TestResponse
{
    return test()->postJson(PLEDGE_GIFTS, [
        'member_id' => test()->member->id, 'fund_type_id' => test()->fund->id, 'pledge_id' => test()->pledge->id,
        'amount' => '300.00', 'payment_method' => 'cash', ...$body,
    ], ['Idempotency-Key' => 'gift-'.Str::random(20)]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->finance = User::factory()->for($this->church)->create();
    $this->finance->assignRole('finance-secretary');
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id]);
    $this->pledge = Pledge::factory()->create([
        'church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id,
        'amount_pledged' => '1000.00', 'target_date' => now()->addMonths(2)->toDateString(),
    ]);
    Sanctum::actingAs($this->finance, ['*']);
});

describe('a gift to a pledge (TC-06-15)', function (): void {
    it('adds to what is paid and takes from what is outstanding', function (): void {
        giftToPledge()->assertCreated()->assertJsonPath('data.pledge.id', $this->pledge->id)->assertJsonPath('data.pledge.description', 'Building Fund Pledge');

        $fresh = $this->pledge->fresh();
        expect($fresh->amount_paid)->toBe('300.00')->and($fresh->amount_outstanding)->toBe('700.00')->and($fresh->progress_percent)->toBe(30)->and($fresh->status)->toBe(PledgeStatus::Active);
    });

    it('adds up several gifts', function (): void {
        giftToPledge(['amount' => '300.00']);
        giftToPledge(['amount' => '150.25']);

        expect($this->pledge->fresh()->amount_paid)->toBe('450.25');
    });

    it('fulfils the pledge when it is paid in full', function (): void {
        giftToPledge(['amount' => '600.00']);
        giftToPledge(['amount' => '400.00'])->assertCreated();

        $fresh = $this->pledge->fresh();
        expect($fresh->status)->toBe(PledgeStatus::Fulfilled)->and($fresh->amount_outstanding)->toBe('0.00')->and($fresh->progress_percent)->toBe(100);
    });

    it('allows paying over the amount pledged, but then refuses more', function (): void {
        giftToPledge(['amount' => '1200.00'])->assertCreated();

        $fresh = $this->pledge->fresh();
        expect($fresh->amount_paid)->toBe('1200.00')->and($fresh->amount_outstanding)->toBe('0.00')->and($fresh->status)->toBe(PledgeStatus::Fulfilled);
        giftToPledge(['amount' => '10.00'])->assertUnprocessable()->assertJsonValidationErrors('pledge_id');
    });

    it('revives a lapsed pledge that is paid late', function (): void {
        $this->pledge->update(['target_date' => now()->subMonth()->toDateString(), 'status' => PledgeStatus::Lapsed]);

        giftToPledge(['amount' => '100.00'])->assertCreated();
        expect($this->pledge->fresh()->status)->toBe(PledgeStatus::Partial);

        giftToPledge(['amount' => '900.00'])->assertCreated();
        expect($this->pledge->fresh()->status)->toBe(PledgeStatus::Fulfilled);
    });

    it('leaves a pledge alone when the gift does not name it', function (): void {
        giftToPledge(['pledge_id' => null])->assertCreated()->assertJsonPath('data.pledge', null);

        expect($this->pledge->fresh()->amount_paid)->toBe('0.00');
    });

    it('rejects a pledge the gift cannot pay', function (string $case): void {
        $this->other = Church::factory()->create(['slug' => 'other']);
        $pledgeId = match ($case) {
            'unknown' => '01j8k2m4n5p6q7r8s9t0u1v2w3',
            'another church' => Pledge::factory()->create(['church_id' => $this->other->id])->id,
            default => $this->pledge->id,
        };
        $body = match ($case) {
            'another member' => ['member_id' => Member::factory()->create(['church_id' => $this->church->id])->id],
            'no member' => ['member_id' => null],
            'another fund' => ['fund_type_id' => FundType::factory()->create(['church_id' => $this->church->id])->id],
            default => [],
        };

        giftToPledge(['pledge_id' => $pledgeId, ...$body])->assertUnprocessable()->assertJsonValidationErrors('pledge_id');

        expect(Contribution::query()->count())->toBe(0)->and($this->pledge->fresh()->amount_paid)->toBe('0.00');
    })->with(['unknown', 'another church', 'another member', 'no member', 'another fund']);

    it('writes the pledge into the gift\'s change history', function (): void {
        $id = giftToPledge()->json('data.id');

        expect(AuditLog::query()->where('auditable_id', $id)->sole()->new_values['pledge_id'])->toBe($this->pledge->id);
    });
});

describe('a voided gift (TC-06-16)', function (): void {
    it('takes the amount back off the pledge', function (): void {
        $id = giftToPledge(['amount' => '300.00'])->json('data.id');
        expect($this->pledge->fresh()->amount_paid)->toBe('300.00');

        $this->patchJson(PLEDGE_GIFTS.'/'.$id.'/void', ['reason' => 'Entered twice'])->assertOk();

        $fresh = $this->pledge->fresh();
        expect($fresh->amount_paid)->toBe('0.00')->and($fresh->amount_outstanding)->toBe('1000.00')->and($fresh->status)->toBe(PledgeStatus::Active);
    });

    it('takes back only that gift', function (): void {
        giftToPledge(['amount' => '300.00']);
        $second = giftToPledge(['amount' => '200.00'])->json('data.id');

        $this->patchJson(PLEDGE_GIFTS.'/'.$second.'/void', ['reason' => 'Wrong amount'])->assertOk();

        expect($this->pledge->fresh()->amount_paid)->toBe('300.00');
    });

    it('moves a fulfilled pledge back when a gift that fulfilled it is voided', function (): void {
        $id = giftToPledge(['amount' => '1000.00'])->json('data.id');
        expect($this->pledge->fresh()->status)->toBe(PledgeStatus::Fulfilled);

        $this->patchJson(PLEDGE_GIFTS.'/'.$id.'/void', ['reason' => 'Bounced'])->assertOk();

        expect($this->pledge->fresh()->status)->toBe(PledgeStatus::Active);
    });

    it('moves a fulfilled pledge past its date to partial or lapsed', function (): void {
        $first = giftToPledge(['amount' => '400.00'])->json('data.id');
        $second = giftToPledge(['amount' => '600.00'])->json('data.id');
        $this->pledge->update(['target_date' => now()->subDay()->toDateString()]);

        $this->patchJson(PLEDGE_GIFTS.'/'.$second.'/void', ['reason' => 'Bounced'])->assertOk();
        expect($this->pledge->fresh()->status)->toBe(PledgeStatus::Partial);

        $this->patchJson(PLEDGE_GIFTS.'/'.$first.'/void', ['reason' => 'Also bounced'])->assertOk();
        expect($this->pledge->fresh()->status)->toBe(PledgeStatus::Lapsed);
    });

    it('does not touch a pledge when the gift paid none', function (): void {
        $this->pledge->update(['amount_paid' => '123.00']);
        $id = giftToPledge(['pledge_id' => null])->json('data.id');

        $this->patchJson(PLEDGE_GIFTS.'/'.$id.'/void', ['reason' => 'Entered twice'])->assertOk();

        expect($this->pledge->fresh()->amount_paid)->toBe('123.00');
    });

    it('keeps a double void from taking the amount off twice', function (): void {
        $id = giftToPledge(['amount' => '300.00'])->json('data.id');
        giftToPledge(['amount' => '200.00']);

        $this->patchJson(PLEDGE_GIFTS.'/'.$id.'/void', ['reason' => 'First'])->assertOk();
        $this->patchJson(PLEDGE_GIFTS.'/'.$id.'/void', ['reason' => 'Second'])->assertUnprocessable();

        expect($this->pledge->fresh()->amount_paid)->toBe('200.00');
    });
});

describe('the daily job', function (): void {
    it('moves active pledges past their date to partial or lapsed, in every church, and leaves the rest', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        $lapsed = Pledge::factory()->create(['church_id' => $this->church->id, 'target_date' => now()->subDay()->toDateString()]);
        $partial = Pledge::factory()->create(['church_id' => $other->id, 'target_date' => now()->subDays(5)->toDateString(), 'amount_paid' => '50.00']);
        Contribution::factory()->create(['church_id' => $other->id, 'fund_type_id' => $partial->fund_type_id, 'member_id' => $partial->member_id, 'pledge_id' => $partial->id, 'amount' => '50.00']);
        $future = Pledge::factory()->create(['church_id' => $this->church->id, 'target_date' => now()->addDay()->toDateString()]);
        $today = Pledge::factory()->create(['church_id' => $this->church->id, 'target_date' => now()->toDateString()]);
        $fulfilled = Pledge::factory()->create(['church_id' => $this->church->id, 'target_date' => now()->subDay()->toDateString(), 'status' => PledgeStatus::Fulfilled, 'amount_paid' => '1000.00']);

        $this->artisan('pledges:refresh')->expectsOutputToContain('Looked at 2 overdue pledge(s).')->assertSuccessful();

        expect($lapsed->fresh()->status)->toBe(PledgeStatus::Lapsed)
            ->and($partial->fresh()->status)->toBe(PledgeStatus::Partial)->and($partial->fresh()->amount_paid)->toBe('50.00')
            ->and($future->fresh()->status)->toBe(PledgeStatus::Active)
            ->and($today->fresh()->status)->toBe(PledgeStatus::Active)
            ->and($fulfilled->fresh()->status)->toBe(PledgeStatus::Fulfilled);
    });

    it('leaves no church in context afterwards, and can be run again', function (): void {
        Pledge::factory()->create(['church_id' => $this->church->id, 'target_date' => now()->subDay()->toDateString()]);

        $this->artisan('pledges:refresh')->assertSuccessful();
        expect(app()->bound('current.church'))->toBeFalse();

        $this->artisan('pledges:refresh')->expectsOutputToContain('Looked at 0 overdue pledge(s).')->assertSuccessful();
    });

    it('runs every day at 04:00 UTC', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'pledges:refresh'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('0 4 * * *')->and($event->timezone)->toBe('UTC');
    });
});
