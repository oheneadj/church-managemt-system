<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

// The Sunday offering report (docs/11 story 4.12, docs/04 Sunday Offering Report, docs/08, QA-06 TC-06-17, QA-10):
// what one service received, by fund and payment method, with voided gifts left out of every number.

/** The report address of a service. */
function offeringReportUrl(Service $service): string
{
    return 'http://grace.localhost/api/v1/services/'.$service->id.'/offering-report';
}

/** A staff member of the church with one role. */
function offeringReportStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Records a gift straight into the database, the way earlier tests do, at the test service. */
function serviceGift(FundType $fund, string $amount, array $overrides = []): Contribution
{
    return Contribution::factory()->create([
        'church_id' => test()->church->id, 'fund_type_id' => $fund->id, 'service_id' => test()->service->id,
        'amount' => $amount, ...$overrides,
    ]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->service = Service::factory()->create(['church_id' => $this->church->id, 'name' => 'Sunday Morning Service', 'service_date' => '2026-10-25']);
    $this->tithe = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe', 'sort_order' => 0]);
    $this->offering = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Offering', 'sort_order' => 1]);
    $this->building = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund', 'sort_order' => 2]);
    $this->finance = offeringReportStaff($this->church, 'finance-secretary');
    Sanctum::actingAs($this->finance, ['*']);
});

describe('the figures (TC-06-17)', function (): void {
    it('adds up each fund, leaves the voided gift out and counts only what stands', function (): void {
        serviceGift($this->tithe, '200.00');
        serviceGift($this->tithe, '150.00');
        serviceGift($this->tithe, '100.00', ['is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Entered twice']);
        serviceGift($this->offering, '50.00');
        serviceGift($this->offering, '75.00');
        serviceGift($this->building, '500.00');

        $response = $this->getJson(offeringReportUrl($this->service))->assertOk();

        $response->assertJsonPath('data.service.name', 'Sunday Morning Service')
            ->assertJsonPath('data.service.service_date', '2026-10-25')
            ->assertJsonPath('data.totals.grand_total', '975.00')
            ->assertJsonPath('data.totals.grand_total_formatted', 'GHS 975.00')
            ->assertJsonPath('data.totals.transaction_count', 5);
        $funds = collect($response->json('data.totals.by_fund'))->keyBy('fund_type');
        expect($funds['Tithe']['total'])->toBe('350.00')
            ->and($funds['Offering']['total'])->toBe('125.00')
            ->and($funds['Building Fund']['total'])->toBe('500.00')
            ->and($funds['Tithe']['total_formatted'])->toBe('GHS 350.00')
            ->and(collect($response->json('data.totals.by_fund'))->pluck('fund_type')->all())->toBe(['Tithe', 'Offering', 'Building Fund']);
    });

    it('splits each fund and the whole by payment method', function (): void {
        serviceGift($this->tithe, '1200.00');
        serviceGift($this->tithe, '850.00', ['payment_method' => PaymentMethod::Momo]);
        serviceGift($this->offering, '430.00');
        serviceGift($this->offering, '220.00', ['payment_method' => PaymentMethod::Momo]);
        serviceGift($this->building, '150.00', ['payment_method' => PaymentMethod::Card]);

        $totals = $this->getJson(offeringReportUrl($this->service))->assertOk()->json('data.totals');

        $tithe = collect($totals['by_fund'])->firstWhere('fund_type', 'Tithe');
        expect($tithe['by_method'])->toEqual(['cash' => '1200.00', 'momo' => '850.00', 'card' => '0.00', 'ussd' => '0.00'])
            ->and($tithe['total'])->toBe('2050.00')
            ->and($totals['by_method']['cash'])->toBe('1630.00')
            ->and($totals['by_method']['momo'])->toBe('1070.00')
            ->and($totals['by_method']['card'])->toBe('150.00')
            ->and($totals['by_method']['ussd'])->toBe('0.00')
            ->and($totals['grand_total'])->toBe('2850.00')
            ->and($totals['transaction_count'])->toBe(5);
    });

    it('counts the gifts with no member', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id]);
        serviceGift($this->tithe, '10.00', ['member_id' => $member->id]);
        serviceGift($this->tithe, '20.00');
        serviceGift($this->tithe, '30.00');
        serviceGift($this->tithe, '40.00', ['is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Mistake']);

        $this->getJson(offeringReportUrl($this->service))->assertOk()->assertJsonPath('data.totals.anonymous_count', 2)->assertJsonPath('data.totals.transaction_count', 3);
    });

    it('is all zeros for a service nothing was given at', function (): void {
        $response = $this->getJson(offeringReportUrl($this->service))->assertOk();

        $response->assertJsonPath('data.totals.grand_total', '0.00')->assertJsonPath('data.totals.by_fund', [])
            ->assertJsonPath('data.totals.transaction_count', 0)->assertJsonPath('data.totals.anonymous_count', 0)
            ->assertJsonPath('data.totals.by_method.cash', '0.00');
    });

    it('stays exact to the pesewa where floats would drift', function (): void {
        foreach (range(1, 30) as $ignored) {
            serviceGift($this->tithe, '0.10');
        }
        serviceGift($this->tithe, '123456.78');

        $this->getJson(offeringReportUrl($this->service))->assertOk()->assertJsonPath('data.totals.grand_total', '123459.78')->assertJsonPath('data.totals.grand_total_formatted', 'GHS 123,459.78');
    });

    it('does not mix in gifts from another service, another church, or gifts with no service', function (): void {
        serviceGift($this->tithe, '100.00');
        $second = Service::factory()->create(['church_id' => $this->church->id]);
        serviceGift($this->tithe, '900.00', ['service_id' => $second->id]);
        serviceGift($this->tithe, '800.00', ['service_id' => null]);
        $foreignService = Service::factory()->create(['church_id' => $this->other->id]);
        Contribution::factory()->create(['church_id' => $this->other->id, 'service_id' => $foreignService->id, 'amount' => '700.00']);

        $this->getJson(offeringReportUrl($this->service))->assertOk()->assertJsonPath('data.totals.grand_total', '100.00')->assertJsonPath('data.totals.transaction_count', 1);
    });

    it('agrees with the list of the same gifts', function (): void {
        serviceGift($this->tithe, '33.33');
        serviceGift($this->offering, '66.67');
        serviceGift($this->building, '10.10', ['is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Mistake']);

        $listed = collect($this->getJson('http://grace.localhost/api/v1/contributions?service_id='.$this->service->id)->assertOk()->json('data'))->pluck('amount')
            ->reduce(fn (string $sum, string $amount) => bcadd($sum, $amount, 2), '0.00');

        expect($this->getJson(offeringReportUrl($this->service))->json('data.totals.grand_total'))->toBe($listed)->and($listed)->toBe('100.00');
    });

    it('holds totals only, with no names or ids of givers', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Emmanuel', 'last_name' => 'Asante']);
        serviceGift($this->tithe, '10.00', ['member_id' => $member->id]);

        $body = $this->getJson(offeringReportUrl($this->service))->assertOk()->getContent();

        expect($body)->not->toContain('Emmanuel')->and($body)->not->toContain($member->id)->and($body)->not->toContain('church_id');
    });
});

describe('authentication and roles (QA-10)', function (): void {
    it('rejects a request without a token', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(offeringReportUrl($this->service))->assertUnauthorized();
    });

    it('lets the church admin, the finance secretary and the pastor read it, and no one else', function (string $role, bool $allowed): void {
        Sanctum::actingAs(offeringReportStaff($this->church, $role), ['*']);

        $this->getJson(offeringReportUrl($this->service))->assertStatus($allowed ? 200 : 403);
    })->with([['church-admin', true], ['finance-secretary', true], ['pastor', true], ['department-head', false], ['youth-pastor', false]]);

    it('needs giving.summary, not the right to see single gifts', function (): void {
        $viewer = User::factory()->for($this->church)->create();
        $viewer->givePermissionTo('giving.view');
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson(offeringReportUrl($this->service))->assertForbidden();
    });
});

describe('another church (isolation, QA-02)', function (): void {
    it('is a 404 to ask for the report of a service of another church', function (): void {
        $foreign = Service::factory()->create(['church_id' => $this->other->id]);
        Contribution::factory()->create(['church_id' => $this->other->id, 'service_id' => $foreign->id, 'amount' => '700.00']);

        $this->getJson('http://grace.localhost/api/v1/services/'.$foreign->id.'/offering-report')->assertNotFound();
    });

    it('is refused by the policy itself for a service of another church', function (): void {
        $foreign = Service::factory()->create(['church_id' => $this->other->id]);

        expect(Gate::forUser($this->finance)->allows('viewOfferingReport', $foreign))->toBeFalse();
    });
});

describe('gifts with no service (D-048)', function (): void {
    it('counts the gifts given that day with no service, apart from the service\'s own, and adds both for the day', function (): void {
        serviceGift($this->tithe, '200.00');
        serviceGift($this->tithe, '50.00', ['service_id' => null, 'payment_method' => PaymentMethod::Momo, 'contributed_at' => '2026-10-25 09:30:00']);
        serviceGift($this->offering, '25.50', ['service_id' => null, 'payment_method' => PaymentMethod::Card, 'contributed_at' => '2026-10-25 20:00:00', 'member_id' => null]);

        $data = $this->getJson(offeringReportUrl($this->service))->assertOk()->json('data');

        expect($data['totals']['grand_total'])->toBe('200.00')
            ->and($data['no_service']['counted_here'])->toBeTrue()
            ->and($data['no_service']['grand_total'])->toBe('75.50')
            ->and($data['no_service']['grand_total_formatted'])->toBe('GHS 75.50')
            ->and($data['no_service']['by_method']['momo'])->toBe('50.00')
            ->and($data['no_service']['by_method']['card'])->toBe('25.50')
            ->and($data['no_service']['transaction_count'])->toBe(2)
            ->and($data['no_service']['anonymous_count'])->toBe(2)
            ->and(collect($data['no_service']['by_fund'])->pluck('fund_type')->all())->toBe(['Tithe', 'Offering'])
            ->and($data['day_grand_total'])->toBe('275.50')
            ->and($data['day_grand_total_formatted'])->toBe('GHS 275.50');
    });

    it('leaves out voided gifts, gifts of other days, gifts at another service and other churches\' gifts', function (): void {
        serviceGift($this->tithe, '10.00', ['service_id' => null, 'contributed_at' => '2026-10-25 08:00:00']);
        serviceGift($this->tithe, '20.00', ['service_id' => null, 'contributed_at' => '2026-10-25 08:00:00', 'is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Mistake']);
        serviceGift($this->tithe, '30.00', ['service_id' => null, 'contributed_at' => '2026-10-24 23:59:59']);
        serviceGift($this->tithe, '40.00', ['service_id' => null, 'contributed_at' => '2026-10-26 00:00:00']);
        $second = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-26']);
        serviceGift($this->tithe, '50.00', ['service_id' => $second->id, 'contributed_at' => '2026-10-25 09:00:00']);
        Contribution::factory()->create(['church_id' => $this->other->id, 'service_id' => null, 'amount' => '60.00', 'contributed_at' => '2026-10-25 08:00:00']);

        $data = $this->getJson(offeringReportUrl($this->service))->assertOk()->json('data');

        expect($data['no_service']['grand_total'])->toBe('10.00')->and($data['no_service']['transaction_count'])->toBe(1)->and($data['totals']['grand_total'])->toBe('0.00');
    });

    it('uses the church\'s own day, not the UTC day', function (): void {
        $this->church->update(['timezone' => 'Asia/Tokyo']);
        // 2026-10-24 16:00 UTC is 01:00 on 25 October in Tokyo, and 2026-10-25 15:30 UTC is 00:30 on the 26th.
        serviceGift($this->tithe, '11.00', ['service_id' => null, 'contributed_at' => '2026-10-24 16:00:00']);
        serviceGift($this->tithe, '22.00', ['service_id' => null, 'contributed_at' => '2026-10-25 15:30:00']);

        $this->getJson(offeringReportUrl($this->service))->assertOk()->assertJsonPath('data.no_service.grand_total', '11.00');
    });

    it('goes with the day\'s first service only, so a day with two services never counts a gift twice', function (): void {
        $this->service->update(['starts_at' => '09:00:00']);
        $early = Service::factory()->create(['church_id' => $this->church->id, 'name' => 'Early', 'service_date' => '2026-10-25', 'starts_at' => '07:00:00']);
        $evening = Service::factory()->create(['church_id' => $this->church->id, 'name' => 'Evening', 'service_date' => '2026-10-25', 'starts_at' => null]);
        serviceGift($this->tithe, '80.00', ['service_id' => null, 'contributed_at' => '2026-10-25 10:00:00']);

        $reports = collect([$early, $this->service, $evening])->map(fn (Service $s) => $this->getJson(offeringReportUrl($s))->assertOk()->json('data'));

        expect($reports->pluck('no_service.counted_here')->all())->toBe([true, false, false])
            ->and($reports->pluck('no_service.grand_total')->all())->toBe(['80.00', '0.00', '0.00'])
            ->and($reports->pluck('day_grand_total')->all())->toBe(['80.00', '0.00', '0.00']);
    });

    it('puts a service with no start time after the ones that have one, and breaks a tie by id', function (): void {
        $this->service->update(['starts_at' => null]);
        $timed = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-25', 'starts_at' => '18:00:00']);

        expect($this->getJson(offeringReportUrl($timed))->json('data.no_service.counted_here'))->toBeTrue()
            ->and($this->getJson(offeringReportUrl($this->service))->json('data.no_service.counted_here'))->toBeFalse();

        $timed->update(['starts_at' => null]);
        $firstById = collect([$timed, $this->service])->sortBy('id')->first();
        expect($this->getJson(offeringReportUrl($firstById))->json('data.no_service.counted_here'))->toBeTrue();
    });

    it('is zeros when nothing was given without a service', function (): void {
        $this->getJson(offeringReportUrl($this->service))->assertOk()
            ->assertJsonPath('data.no_service.grand_total', '0.00')->assertJsonPath('data.no_service.by_fund', [])
            ->assertJsonPath('data.day_grand_total', '0.00');
    });
});

describe('fund names (D-048)', function (): void {
    it('keeps the name a gift was given to when the fund is renamed later', function (): void {
        $before = serviceGift($this->tithe, '100.00');
        $this->tithe->update(['name' => 'Tithes and Firstfruits']);
        serviceGift($this->tithe, '50.00', ['fund_name' => 'Tithes and Firstfruits']);

        $funds = collect($this->getJson(offeringReportUrl($this->service))->assertOk()->json('data.totals.by_fund'));

        expect($funds->pluck('fund_type')->all())->toBe(['Tithe', 'Tithes and Firstfruits'])
            ->and($funds->pluck('total')->all())->toBe(['100.00', '50.00'])
            ->and($this->getJson('http://grace.localhost/api/v1/contributions/'.$before->id)->json('data.fund_type'))->toBe(['id' => $this->tithe->id, 'name' => 'Tithe']);
    });

    it('keeps old reports the same after a rename', function (): void {
        serviceGift($this->offering, '125.00');
        $before = $this->getJson(offeringReportUrl($this->service))->json('data.totals');

        $this->offering->update(['name' => 'General Offering']);

        expect($this->getJson(offeringReportUrl($this->service))->json('data.totals'))->toBe($before);
    });

    it('records the name when a cash gift is recorded, and a rename afterwards does not change it', function (): void {
        Sanctum::actingAs($this->finance, ['*']);
        $id = $this->postJson('http://grace.localhost/api/v1/contributions', ['fund_type_id' => $this->tithe->id, 'amount' => '10.00', 'payment_method' => 'cash', 'service_id' => $this->service->id], ['Idempotency-Key' => 'name-snapshot-0001'])
            ->assertCreated()->assertJsonPath('data.fund_type.name', 'Tithe')->json('data.id');

        $this->tithe->update(['name' => 'Renamed']);

        expect(Contribution::query()->find($id)->fund_name)->toBe('Tithe')
            ->and($this->getJson('http://grace.localhost/api/v1/contributions/'.$id)->json('data.fund_type.name'))->toBe('Tithe');
    });

    it('fills the name on gifts that have none, in every church, and can be run again', function (): void {
        Schema::table('contributions', fn ($table) => $table->string('fund_name', 100)->nullable()->change());
        $a = serviceGift($this->tithe, '1.00');
        $foreignFund = FundType::factory()->create(['church_id' => $this->other->id, 'name' => 'Their Fund']);
        $b = Contribution::factory()->create(['church_id' => $this->other->id, 'fund_type_id' => $foreignFund->id, 'amount' => '2.00']);
        DB::table('contributions')->whereIn('id', [$a->id, $b->id])->update(['fund_name' => null]);
        $this->tithe->update(['name' => 'Tithe Now']);

        $this->artisan('contributions:backfill-fund-names')->expectsOutputToContain('Filled the fund name on 2 gift(s).')->assertSuccessful();

        expect(DB::table('contributions')->where('id', $a->id)->value('fund_name'))->toBe('Tithe Now')
            ->and(DB::table('contributions')->where('id', $b->id)->value('fund_name'))->toBe('Their Fund');
        $this->artisan('contributions:backfill-fund-names')->expectsOutputToContain('Filled the fund name on 0 gift(s).')->assertSuccessful();
    });

    it('refuses to make the column required while some gifts have no name', function (): void {
        Schema::table('contributions', fn ($table) => $table->string('fund_name', 100)->nullable()->change());
        $gift = serviceGift($this->tithe, '1.00');
        DB::table('contributions')->where('id', $gift->id)->update(['fund_name' => null]);
        $migration = require database_path('migrations/2026_10_28_235357_make_fund_name_required_on_contributions_table.php');

        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'contributions:backfill-fund-names');
    });
});
