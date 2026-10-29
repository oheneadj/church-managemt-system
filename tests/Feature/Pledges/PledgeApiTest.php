<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\MemberStatus;
use App\Enums\PledgeStatus;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Pledges (docs/11 stories 4.9 and 4.10, docs/04 Pledge Tracking, docs/08 Pledges, QA-06 TC-06-14, D-047).

const PLEDGES = 'http://grace.localhost/api/v1/pledges';

/** A staff member of the church with one role. */
function pledgeStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A valid pledge body. */
function pledgeBody(array $overrides = []): array
{
    return [
        'member_id' => test()->member->id,
        'fund_type_id' => test()->fund->id,
        'description' => 'Building Fund 2026 Pledge',
        'amount_pledged' => '1000.00',
        'target_date' => now()->addMonths(2)->toDateString(),
        ...$overrides,
    ];
}

/** Makes a pledge with a fresh Idempotency-Key unless one is given. */
function makePledge(array $body = [], ?string $key = null): TestResponse
{
    return test()->postJson(PLEDGES, pledgeBody($body), ['Idempotency-Key' => $key ?? 'pledge-'.Str::random(20)]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->finance = pledgeStaff($this->church, 'finance-secretary');
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Emmanuel', 'last_name' => 'Asante']);
    Sanctum::actingAs($this->finance, ['*']);
});

describe('making a pledge (TC-06-14)', function (): void {
    it('starts active with nothing paid and the whole amount outstanding', function (): void {
        $response = makePledge()->assertCreated();

        $response->assertJsonPath('data.member.full_name', 'Emmanuel Asante')
            ->assertJsonPath('data.fund_type.name', 'Building Fund')
            ->assertJsonPath('data.description', 'Building Fund 2026 Pledge')
            ->assertJsonPath('data.amount_pledged', '1000.00')
            ->assertJsonPath('data.amount_pledged_formatted', 'GHS 1,000.00')
            ->assertJsonPath('data.amount_paid', '0.00')
            ->assertJsonPath('data.amount_outstanding', '1000.00')
            ->assertJsonPath('data.progress_percent', 0)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_label', 'Active');
        expect($response->json('data.id'))->toMatch('/^[0-9a-z]{26}$/');
        $pledge = Pledge::query()->sole();
        expect($pledge->church_id)->toBe($this->church->id)->and($pledge->created_by)->toBe($this->finance->id);
    });

    it('writes a change-history line', function (): void {
        $id = makePledge()->json('data.id');

        $line = AuditLog::query()->where('auditable_id', $id)->sole();
        expect($line->event)->toBe(AuditEvent::Created)->and($line->actor_id)->toBe($this->finance->id)->and($line->new_values['amount_pledged'])->toBe('1000.00');
    });

    it('accepts a target date of today', function (): void {
        makePledge(['target_date' => now()->toDateString()])->assertCreated();
    });

    it('rejects bad values', function (array $bad, string $field): void {
        makePledge($bad)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Pledge::query()->count())->toBe(0);
    })->with([
        'no member' => [['member_id' => null], 'member_id'],
        'no fund' => [['fund_type_id' => null], 'fund_type_id'],
        'no description' => [['description' => ''], 'description'],
        'description too long' => [['description' => str_repeat('a', 256)], 'description'],
        'zero amount' => [['amount_pledged' => 0], 'amount_pledged'],
        'negative amount' => [['amount_pledged' => -5], 'amount_pledged'],
        'three decimals' => [['amount_pledged' => '10.005'], 'amount_pledged'],
        'too large' => [['amount_pledged' => '1000000.00'], 'amount_pledged'],
        'no date' => [['target_date' => null], 'target_date'],
        'bad date format' => [['target_date' => '31/12/2026'], 'target_date'],
        'a date in the past' => [['target_date' => '2020-01-01'], 'target_date'],
    ]);

    it('refuses a member who has died, but takes one who is inactive', function (): void {
        $this->member->update(['status' => MemberStatus::Deceased]);
        makePledge()->assertUnprocessable()->assertJsonValidationErrors('member_id');

        $this->member->update(['status' => MemberStatus::Inactive]);
        makePledge()->assertCreated();
    });

    it('refuses a fund that is switched off', function (): void {
        $this->fund->update(['is_active' => false]);

        makePledge()->assertUnprocessable()->assertJsonValidationErrors('fund_type_id');
    });

    it('refuses a member or fund of another church (isolation)', function (string $field): void {
        $foreign = $field === 'member_id' ? Member::factory()->create(['church_id' => $this->other->id])->id : FundType::factory()->create(['church_id' => $this->other->id])->id;

        makePledge([$field => $foreign])->assertUnprocessable()->assertJsonValidationErrors($field);
        expect(Pledge::query()->withoutGlobalScopes()->count())->toBe(0);
    })->with(['member_id', 'fund_type_id']);

    it('makes one pledge when the same key arrives twice, and needs a key', function (): void {
        makePledge([], 'same-pledge-key')->assertCreated();
        makePledge([], 'same-pledge-key')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        expect(Pledge::query()->count())->toBe(1);

        $this->postJson(PLEDGES, pledgeBody())->assertStatus(400);
    });
});

describe('listing and reading', function (): void {
    it('lists the pledges due soonest first, in pages, with how far along each is', function (): void {
        $later = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id, 'target_date' => '2027-06-30']);
        $sooner = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id, 'target_date' => '2026-12-31', 'amount_paid' => '250.00']);

        $response = $this->getJson(PLEDGES.'?per_page=1')->assertOk();

        expect($response->json('data.0.id'))->toBe($sooner->id)
            ->and($response->json('data.0.progress_percent'))->toBe(25)
            ->and($response->json('data.0.amount_outstanding_formatted'))->toBe('GHS 750.00')
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('data.0'))->not->toHaveKey('church_id')
            ->and($later->id)->not->toBe($sooner->id);
    });

    it('filters by member, status and fund', function (): void {
        $other = Member::factory()->create(['church_id' => $this->church->id]);
        $otherFund = FundType::factory()->create(['church_id' => $this->church->id]);
        $match = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id]);
        Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $other->id, 'fund_type_id' => $this->fund->id]);
        Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $otherFund->id, 'status' => PledgeStatus::Lapsed]);

        $ids = fn (string $query) => collect($this->getJson(PLEDGES.$query)->assertOk()->json('data'))->pluck('id')->all();

        expect($ids('?member_id='.$this->member->id.'&fund_type_id='.$this->fund->id))->toBe([$match->id])
            ->and($ids('?status=lapsed'))->toHaveCount(1)
            ->and($ids('?status=fulfilled'))->toBe([]);
    });

    it('rejects bad filters', function (string $query, string $field): void {
        $this->getJson(PLEDGES.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([['?status=done', 'status'], ['?per_page=0', 'per_page'], ['?member_id=abc', 'member_id']]);

    it('shows one pledge with every gift that pays it, voided ones marked', function (): void {
        $pledge = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id]);
        $kept = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'pledge_id' => $pledge->id, 'amount' => '300.00', 'contributed_at' => now()->subDay()]);
        $voided = Contribution::factory()->voided()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'pledge_id' => $pledge->id, 'amount' => '50.00', 'contributed_at' => now()->subDays(2)]);

        $response = $this->getJson(PLEDGES.'/'.$pledge->id)->assertOk();

        expect(collect($response->json('data.contributions'))->pluck('id')->all())->toBe([$kept->id, $voided->id])
            ->and($response->json('data.contributions.1.is_voided'))->toBeTrue()
            ->and($response->json('data.contributions.0.amount'))->toBe('300.00');
    });

    it('lists only this church\'s pledges (isolation)', function (): void {
        Pledge::factory()->create(['church_id' => $this->other->id]);

        $this->getJson(PLEDGES)->assertOk()->assertJsonPath('meta.total', 0);
    });
});

describe('changing a pledge (D-047)', function (): void {
    beforeEach(fn () => $this->pledge = Pledge::factory()->create([
        'church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id,
        'amount_pledged' => '1000.00', 'target_date' => now()->addMonths(2)->toDateString(),
    ]));

    it('changes the words, the amount and the date', function (): void {
        $newDate = now()->addMonths(6)->toDateString();

        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['description' => 'Roof', 'amount_pledged' => '1500.00', 'target_date' => $newDate])
            ->assertOk()
            ->assertJsonPath('data.description', 'Roof')
            ->assertJsonPath('data.amount_pledged', '1500.00')
            ->assertJsonPath('data.target_date', $newDate)
            ->assertJsonPath('data.amount_outstanding', '1500.00');
    });

    it('fulfils the pledge when the amount is lowered to what was paid', function (): void {
        $gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'pledge_id' => $this->pledge->id, 'amount' => '400.00']);
        $this->pledge->update(['amount_paid' => $gift->amount]);

        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['amount_pledged' => '400.00'])->assertOk()->assertJsonPath('data.status', 'fulfilled')->assertJsonPath('data.progress_percent', 100);
    });

    it('refuses a new target date in the past, but takes today or later (the owner asked for this, Oct 2026)', function (): void {
        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['target_date' => now()->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('target_date');
        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['target_date' => now()->toDateString()])->assertOk();
    });

    it('lets a pledge whose date has already passed be saved again with the same date', function (): void {
        $this->pledge->update(['target_date' => now()->subMonth()->toDateString(), 'status' => PledgeStatus::Lapsed]);

        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['description' => 'Renamed', 'target_date' => now()->subMonth()->toDateString()])->assertOk()->assertJsonPath('data.status', 'lapsed');
    });

    it('cannot change the member or the fund, or the paid amount or status', function (): void {
        $other = Member::factory()->create(['church_id' => $this->church->id]);

        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['member_id' => $other->id, 'amount_paid' => '999.00', 'status' => 'fulfilled', 'description' => 'Still ok'])->assertOk();

        $fresh = $this->pledge->fresh();
        expect($fresh->member_id)->toBe($this->member->id)->and($fresh->amount_paid)->toBe('0.00')->and($fresh->status)->toBe(PledgeStatus::Active);
    });

    it('rejects bad values', function (array $bad, string $field): void {
        $this->putJson(PLEDGES.'/'.$this->pledge->id, $bad)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'empty description' => [['description' => ''], 'description'],
        'zero amount' => [['amount_pledged' => 0], 'amount_pledged'],
        'bad date' => [['target_date' => 'soon'], 'target_date'],
    ]);

    it('writes a change-history line with the old and new values', function (): void {
        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['amount_pledged' => '1200.00'])->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->pledge->id)->where('event', AuditEvent::Updated)->sole();
        expect($line->old_values['amount_pledged'])->toBe('1000.00')->and($line->new_values['amount_pledged'])->toBe('1200.00');
    });

    it('has no way to delete a pledge', function (): void {
        $this->deleteJson(PLEDGES.'/'.$this->pledge->id)->assertStatus(405);

        expect(Pledge::query()->find($this->pledge->id))->not->toBeNull();
    });
});

describe('authentication and roles (D-047)', function (): void {
    beforeEach(fn () => $this->pledge = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'fund_type_id' => $this->fund->id]));

    it('rejects every endpoint without a token', function (string $method, string $path): void {
        $this->app['auth']->forgetGuards();

        $this->json($method, str_replace('{id}', $this->pledge->id, PLEDGES.$path))->assertUnauthorized();
    })->with([['GET', ''], ['POST', ''], ['GET', '/{id}'], ['PUT', '/{id}']]);

    it('lets only the church admin and finance secretary see, make and change pledges', function (string $role, bool $allowed): void {
        Sanctum::actingAs(pledgeStaff($this->church, $role), ['*']);

        $this->getJson(PLEDGES)->assertStatus($allowed ? 200 : 403);
        $this->getJson(PLEDGES.'/'.$this->pledge->id)->assertStatus($allowed ? 200 : 403);
        makePledge()->assertStatus($allowed ? 201 : 403);
        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['description' => 'Changed'])->assertStatus($allowed ? 200 : 403);
    })->with([['church-admin', true], ['finance-secretary', true], ['pastor', false], ['department-head', false], ['youth-pastor', false]]);

    it('keeps seeing, making and changing apart', function (): void {
        $viewer = User::factory()->for($this->church)->create();
        $viewer->givePermissionTo('giving.view');
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson(PLEDGES)->assertOk();
        makePledge()->assertForbidden();
        $this->putJson(PLEDGES.'/'.$this->pledge->id, ['description' => 'Changed'])->assertForbidden();
    });
});

describe('another church (isolation, QA-02)', function (): void {
    it('is a 404 to read or change a pledge of another church, and nothing changes', function (): void {
        $stranger = Pledge::factory()->create(['church_id' => $this->other->id, 'description' => 'Theirs']);

        $this->getJson(PLEDGES.'/'.$stranger->id)->assertNotFound();
        $this->putJson(PLEDGES.'/'.$stranger->id, ['description' => 'Mine now'])->assertNotFound();
        expect(Pledge::query()->withoutGlobalScopes()->find($stranger->id)->description)->toBe('Theirs');
    });
});
