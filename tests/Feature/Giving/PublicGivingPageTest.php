<?php

declare(strict_types=1);

use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Models\Church;
use App\Models\FundType;
use App\Models\Member;

// The public giving page (D-073, docs/08 The Public Giving Page): anyone can open it with no sign-in, it shows the church it is
// opened on, its active funds and the limits, and it is "not available" while the church cannot be paid.

const GIVE_PAGE = 'http://grace.localhost/api/v1/give';

beforeEach(function (): void {
    $this->church = Church::factory()->create([
        'slug' => 'grace', 'name' => 'Grace Assembly', 'payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn,
        'payout_account_number' => '+233244000111', 'payout_account_name' => 'GRACE', 'payout_verified_at' => now(),
    ]);
    $this->other = Church::factory()->create(['slug' => 'other', 'name' => 'Other Church']);
});

describe('what the page shows', function (): void {
    it('needs no sign-in and gives the church, its active funds in order, the limits and the fee', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe', 'sort_order' => 1, 'description' => 'One tenth']);
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund', 'sort_order' => 0]);
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Old Fund', 'sort_order' => 2, 'is_active' => false]);

        $response = $this->getJson(GIVE_PAGE)->assertOk()
            ->assertJsonPath('data.church_name', 'Grace Assembly')->assertJsonPath('data.available', true)
            ->assertJsonPath('data.min_gift', '1.00')->assertJsonPath('data.min_gift_formatted', 'GHS 1.00')
            ->assertJsonPath('data.max_gift', '30000.00')->assertJsonPath('data.max_gift_formatted', 'GHS 30,000.00')
            ->assertJsonPath('data.processing_fee_percent', '1.99');

        expect(collect($response->json('data.funds'))->pluck('name')->all())->toBe(['Building Fund', 'Tithe'])
            ->and($response->json('data.funds.1.description'))->toBe('One tenth')
            ->and(array_keys($response->json('data.funds.0')))->toBe(['id', 'name', 'description']);
    });

    it('says it is not available, with no funds, while the church has no verified payout account', function (string $state): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
        $this->church->update(match ($state) {
            'none' => ['payout_method' => null, 'payout_network' => null, 'payout_account_number' => null, 'payout_verified_at' => null],
            'checking' => ['payout_verified_at' => null],
            'failed' => ['payout_verified_at' => null, 'payout_check_error' => 'Could not name the account.'],
        });

        $this->getJson(GIVE_PAGE)->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.funds', [])->assertJsonPath('data.church_name', 'Grace Assembly');
    })->with(['none', 'checking', 'failed']);

    it('shows nothing about the church\'s people, money or account', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);

        $body = $this->getJson(GIVE_PAGE)->assertOk()->getContent();

        expect($body)->not->toContain('payout')->not->toContain('233244000111')->not->toContain('GRACE"')->not->toContain('sms_credits');
    });
});

describe('which church it is', function (): void {
    it('is the church the address names, never another church\'s funds (isolation)', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
        FundType::factory()->create(['church_id' => $this->other->id, 'name' => 'Other Fund']);

        $names = collect($this->getJson(GIVE_PAGE)->assertOk()->json('data.funds'))->pluck('name')->all();

        expect($names)->toBe(['Tithe']);
    });

    it('is a 404 on an address that belongs to no church', function (): void {
        $this->getJson('http://nobody.localhost/api/v1/give')->assertNotFound();
    });

    it('cannot be pointed at another church by anything the visitor sends', function (): void {
        FundType::factory()->create(['church_id' => $this->other->id, 'name' => 'Other Fund']);

        $this->getJson(GIVE_PAGE.'?church_id='.$this->other->id.'&slug=other')->assertOk()->assertJsonPath('data.church_name', 'Grace Assembly')->assertJsonPath('data.funds', []);
    });
});

describe('protection', function (): void {
    it('limits how often one address may read it, and answers in plain words', function (): void {
        config(['limits.public_give_page_per_minute' => 2]);

        $this->getJson(GIVE_PAGE)->assertOk();
        $this->getJson(GIVE_PAGE)->assertOk();
        $this->getJson(GIVE_PAGE)->assertStatus(429)->assertJsonPath('message', 'Too many people are opening this page from here right now. Please wait a moment and try again.');
    });
});
