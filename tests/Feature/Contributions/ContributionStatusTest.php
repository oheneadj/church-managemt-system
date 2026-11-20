<?php

declare(strict_types=1);

use App\Enums\ContributionStatus;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\Service;
use App\Models\User;
use App\Services\PledgeProgressService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// A gift has a status (docs/07 contributions.status, D-053, D-069 step d): only a completed gift that is not voided is
// ever counted. A digital gift is pending while the provider has not confirmed, and failed if it never will.

/** One gift at the test service, of the test fund. */
function statusGift(string $amount, array $overrides = []): Contribution
{
    return Contribution::factory()->create([
        'church_id' => test()->church->id, 'fund_type_id' => test()->fund->id, 'service_id' => test()->service->id,
        'member_id' => test()->member->id, 'amount' => $amount, ...$overrides,
    ]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->service = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-25']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id]);
    $user = User::factory()->for($this->church)->create();
    $user->assignRole('finance-secretary');
    Sanctum::actingAs($user, ['*']);
});

describe('the column', function (): void {
    it('is completed for a gift recorded without saying, and for cash recorded through the API', function (): void {
        expect(statusGift('10.00')->fresh()->status)->toBe(ContributionStatus::Completed);

        $this->postJson('http://grace.localhost/api/v1/contributions', [
            'fund_type_id' => $this->fund->id, 'amount' => '25.00', 'payment_method' => 'cash', 'contributed_at' => '2026-10-25T10:00:00Z',
        ], ['Idempotency-Key' => 'status-test-key-000001'])->assertCreated();

        expect(Contribution::query()->latest('id')->first()->status)->toBe(ContributionStatus::Completed);
    });

    it('has a label for each status', function (): void {
        expect(collect(ContributionStatus::cases())->map->label()->all())->toBe(['Pending', 'Completed', 'Failed']);
    });
});

describe('what is counted', function (): void {
    it('leaves pending and failed gifts out of the offering report', function (): void {
        statusGift('100.00');
        statusGift('40.00', ['status' => ContributionStatus::Pending]);
        statusGift('60.00', ['status' => ContributionStatus::Failed]);

        $this->getJson('http://grace.localhost/api/v1/services/'.$this->service->id.'/offering-report')
            ->assertOk()
            ->assertJsonPath('data.totals.grand_total', '100.00')
            ->assertJsonPath('data.totals.transaction_count', 1);
    });

    it('leaves them out of the gift list and of the voided list', function (): void {
        statusGift('100.00');
        statusGift('40.00', ['status' => ContributionStatus::Pending]);
        statusGift('60.00', ['status' => ContributionStatus::Failed, 'is_voided' => true]);

        $this->getJson('http://grace.localhost/api/v1/contributions')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('http://grace.localhost/api/v1/contributions?is_voided=1')->assertOk()->assertJsonCount(0, 'data');
    });

    it('leaves them out of the giving report', function (): void {
        statusGift('100.00', ['contributed_at' => now()]);
        statusGift('40.00', ['contributed_at' => now(), 'status' => ContributionStatus::Pending]);

        $report = $this->getJson('http://grace.localhost/api/v1/reports/giving')->assertOk()->json('data');

        expect(json_encode($report))->toContain('100.00')->not->toContain('140.00');
    });

    it('leaves them out of what a pledge has been paid and what a member has given', function (): void {
        $pledge = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id, 'amount_pledged' => '500.00']);
        statusGift('100.00', ['pledge_id' => $pledge->id]);
        statusGift('300.00', ['pledge_id' => $pledge->id, 'status' => ContributionStatus::Pending]);
        statusGift('50.00', ['pledge_id' => $pledge->id, 'status' => ContributionStatus::Failed]);

        expect(app(PledgeProgressService::class)->refresh($pledge->id)->amount_paid)->toBe('100.00');
        $this->getJson('http://grace.localhost/api/v1/members/'.$this->member->id)->assertOk()->assertJsonPath('data.giving_summary.all_time', '100.00');
        $this->getJson('http://grace.localhost/api/v1/pledges/'.$pledge->id)->assertOk()->assertJsonCount(1, 'data.contributions');
    });
});
