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
use Illuminate\Support\Facades\Gate;
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
