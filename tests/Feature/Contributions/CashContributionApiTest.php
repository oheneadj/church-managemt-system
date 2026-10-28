<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use App\Repositories\ContributionRepositoryInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Cash gifts (docs/11 stories 4.3 and 4.11, docs/08 Contributions, QA-06 TC-06-01 to TC-06-05 and TC-06-11 to TC-06-13,
// QA-10 giving matrix). Gifts are recorded once, never deleted, and only voided with a reason.

const GIFTS = 'http://grace.localhost/api/v1/contributions';

/** A staff member of the church with one role. */
function giftStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A valid cash gift body. */
function giftBody(array $overrides = []): array
{
    return ['fund_type_id' => test()->fund->id, 'amount' => '150.00', 'payment_method' => 'cash', ...$overrides];
}

/** Records a gift with a fresh Idempotency-Key unless one is given. */
function recordGift(array $body = [], ?string $key = null): TestResponse
{
    return test()->postJson(GIFTS, giftBody($body), ['Idempotency-Key' => $key ?? 'gift-'.Str::random(20)]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->finance = giftStaff($this->church, 'finance-secretary');
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Emmanuel', 'last_name' => 'Asante']);
    Sanctum::actingAs($this->finance, ['*']);
});

describe('recording a cash gift (TC-06-01)', function (): void {
    it('saves the gift for the member with the fund, amount and who recorded it', function (): void {
        $service = Service::factory()->create(['church_id' => $this->church->id, 'name' => 'Sunday Morning Service']);

        $response = recordGift(['member_id' => $this->member->id, 'service_id' => $service->id, 'notes' => 'Gave at Sunday service'])->assertCreated();

        $response->assertJsonPath('data.amount', '150.00')
            ->assertJsonPath('data.amount_formatted', 'GHS 150.00')
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_method_label', 'Cash')
            ->assertJsonPath('data.member.full_name', 'Emmanuel Asante')
            ->assertJsonPath('data.is_anonymous', false)
            ->assertJsonPath('data.service.name', 'Sunday Morning Service')
            ->assertJsonPath('data.fund_type.name', 'Tithe')
            ->assertJsonPath('data.is_voided', false)
            ->assertJsonPath('data.recorded_by', $this->finance->name)
            ->assertJsonPath('data.notes', 'Gave at Sunday service');
        expect($response->json('data.id'))->toMatch('/^[0-9a-z]{26}$/');
        $gift = Contribution::query()->sole();
        expect($gift->church_id)->toBe($this->church->id)
            ->and($gift->payment_method)->toBe(PaymentMethod::Cash)
            ->and($gift->recorded_by)->toBe($this->finance->id)
            ->and($gift->processing_fee)->toBeNull()
            ->and($gift->paystack_reference)->toBeNull()->and($gift->payperlez_reference)->toBeNull();
    });

    it('keeps the day the giving happened apart from the day it was entered', function (): void {
        recordGift(['contributed_at' => now()->subDays(2)->startOfHour()->toIso8601String()])->assertCreated();

        $gift = Contribution::query()->sole();
        expect($gift->contributed_at->isSameDay(now()->subDays(2)))->toBeTrue()
            ->and($gift->created_at->isSameDay(now()))->toBeTrue();
    });

    it('records an anonymous gift when there is no member (TC-06-03)', function (): void {
        recordGift()->assertCreated()->assertJsonPath('data.member', null)->assertJsonPath('data.is_anonymous', true);
    });

    it('keeps the amount exact to the pesewa', function (string|int|float $sent, string $stored, string $formatted): void {
        recordGift(['amount' => $sent])->assertCreated()->assertJsonPath('data.amount', $stored)->assertJsonPath('data.amount_formatted', $formatted);
    })->with([
        'whole cedis' => [150, '150.00', 'GHS 150.00'],
        'one decimal' => ['200.5', '200.50', 'GHS 200.50'],
        'a float from JSON' => [0.1, '0.10', 'GHS 0.10'],
        'thousands' => ['123456.78', '123456.78', 'GHS 123,456.78'],
        'the largest cash gift' => ['999999.99', '999999.99', 'GHS 999,999.99'],
    ]);

    it('writes a change-history line', function (): void {
        $id = recordGift(['member_id' => $this->member->id])->json('data.id');

        $line = AuditLog::query()->where('auditable_id', $id)->sole();
        expect($line->event)->toBe(AuditEvent::Created)->and($line->actor_id)->toBe($this->finance->id)
            ->and($line->new_values['amount'])->toBe('150.00');
    });
});

describe('validation (TC-06-04, TC-06-05)', function (): void {
    it('rejects bad values', function (array $bad, string $field): void {
        recordGift($bad)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Contribution::query()->count())->toBe(0);
    })->with([
        'negative amount' => [['amount' => -50], 'amount'],
        'zero' => [['amount' => 0], 'amount'],
        'too large' => [['amount' => '9999999.99'], 'amount'],
        'just over the maximum' => [['amount' => '1000000.00'], 'amount'],
        'three decimals' => [['amount' => '10.005'], 'amount'],
        'not a number' => [['amount' => 'ten'], 'amount'],
        'no amount' => [['amount' => null], 'amount'],
        'no fund' => [['fund_type_id' => null], 'fund_type_id'],
        'not an id' => [['fund_type_id' => 'tithe'], 'fund_type_id'],
        'digital method' => [['payment_method' => 'momo'], 'payment_method'],
        'unknown method' => [['payment_method' => 'cheque'], 'payment_method'],
        'no method' => [['payment_method' => null], 'payment_method'],
        'in the future' => [['contributed_at' => '2099-01-01T00:00:00Z'], 'contributed_at'],
        'a date that is not a date' => [['contributed_at' => 'yesterday-ish'], 'contributed_at'],
        'notes too long' => [['notes' => str_repeat('a', 2001)], 'notes'],
    ]);

    it('refuses a fund that is switched off', function (): void {
        $this->fund->update(['is_active' => false]);

        recordGift()->assertUnprocessable()->assertJsonValidationErrors('fund_type_id');
    });

    it('refuses a member who has died, but takes one who is inactive', function (): void {
        $this->member->update(['status' => MemberStatus::Deceased]);
        recordGift(['member_id' => $this->member->id])->assertUnprocessable()->assertJsonValidationErrors('member_id');

        $this->member->update(['status' => MemberStatus::Inactive]);
        recordGift(['member_id' => $this->member->id])->assertCreated();
    });

    it('refuses a member, service or fund of another church (isolation)', function (string $field): void {
        $foreign = match ($field) {
            'member_id' => Member::factory()->create(['church_id' => $this->other->id])->id,
            'service_id' => Service::factory()->create(['church_id' => $this->other->id])->id,
            'fund_type_id' => FundType::factory()->create(['church_id' => $this->other->id])->id,
        };

        recordGift([$field => $foreign])->assertUnprocessable()->assertJsonValidationErrors($field);
        expect(Contribution::query()->withoutGlobalScopes()->count())->toBe(0);
    })->with(['member_id', 'service_id', 'fund_type_id']);

    it('takes the church from the sign-in and ignores one in the body', function (): void {
        recordGift(['church_id' => $this->other->id])->assertCreated();

        expect(Contribution::query()->sole()->church_id)->toBe($this->church->id);
    });
});

describe('a double click (agreement section 11)', function (): void {
    it('records one gift when the same key arrives twice', function (): void {
        $first = recordGift(['member_id' => $this->member->id], 'same-key-0001')->assertCreated();
        $second = recordGift(['member_id' => $this->member->id], 'same-key-0001')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect(Contribution::query()->count())->toBe(1)->and($second->json('data.id'))->toBe($first->json('data.id'));
    });

    it('records two gifts for two keys', function (): void {
        recordGift([], 'key-one-0001');
        recordGift([], 'key-two-0002');

        expect(Contribution::query()->count())->toBe(2);
    });

    it('needs a key', function (): void {
        $this->postJson(GIFTS, giftBody())->assertStatus(400);

        expect(Contribution::query()->count())->toBe(0);
    });
});

describe('listing and reading', function (): void {
    it('lists newest first with the gift, fund and member, in pages', function (): void {
        $old = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'contributed_at' => now()->subDays(3)]);
        $new = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'amount' => '75.50', 'contributed_at' => now()->subDay()]);

        $response = $this->getJson(GIFTS.'?per_page=1')->assertOk();

        expect($response->json('data.0.id'))->toBe($new->id)
            ->and($response->json('data.0.amount_formatted'))->toBe('GHS 75.50')
            ->and($response->json('data.0.member.full_name'))->toBe('Emmanuel Asante')
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('data.0'))->not->toHaveKey('church_id')
            ->and($old->id)->not->toBe($new->id);
    });

    it('leaves voided gifts out by default and shows them on request', function (): void {
        $kept = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id]);
        $voided = Contribution::factory()->voided()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id]);

        expect(collect($this->getJson(GIFTS)->json('data'))->pluck('id')->all())->toBe([$kept->id])
            ->and(collect($this->getJson(GIFTS.'?is_voided=1')->json('data'))->pluck('id')->all())->toBe([$voided->id]);
    });

    it('filters by member, service, fund, method and dates', function (): void {
        $service = Service::factory()->create(['church_id' => $this->church->id]);
        $otherFund = FundType::factory()->create(['church_id' => $this->church->id]);
        $match = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'service_id' => $service->id, 'contributed_at' => '2026-10-14 10:00:00']);
        Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $otherFund->id, 'contributed_at' => '2026-11-14 10:00:00']);
        Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'payment_method' => PaymentMethod::Momo, 'contributed_at' => '2026-10-15 10:00:00']);

        $ids = fn (string $query) => collect($this->getJson(GIFTS.$query)->assertOk()->json('data'))->pluck('id')->all();

        expect($ids('?member_id='.$this->member->id))->toBe([$match->id])
            ->and($ids('?service_id='.$service->id))->toBe([$match->id])
            ->and($ids('?fund_type_id='.$this->fund->id.'&payment_method=cash'))->toBe([$match->id])
            ->and($ids('?from=2026-10-01&to=2026-10-14&payment_method=cash'))->toBe([$match->id])
            ->and($ids('?payment_method=ussd'))->toBe([]);
    });

    it('rejects bad filters', function (string $query, string $field): void {
        $this->getJson(GIFTS.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([['?payment_method=cheque', 'payment_method'], ['?from=2026-10-31&to=2026-10-01', 'to'], ['?per_page=0', 'per_page'], ['?member_id=abc', 'member_id'], ['?from=14/10/2026', 'from']]);

    it('shows one gift by its external id', function (): void {
        $gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id]);

        $this->getJson(GIFTS.'/'.$gift->id)->assertOk()->assertJsonPath('data.id', $gift->id)->assertJsonPath('data.fund_type.name', 'Tithe');
    });

    it('lists only this church\'s gifts (isolation)', function (): void {
        Contribution::factory()->create(['church_id' => $this->other->id]);

        $this->getJson(GIFTS)->assertOk()->assertJsonPath('meta.total', 0);
    });
});

describe('voiding (TC-06-11, TC-06-12, TC-06-13)', function (): void {
    beforeEach(fn () => $this->gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id]));

    it('marks the gift voided with the reason, who and when, and keeps the row', function (): void {
        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'Incorrect amount entered'])
            ->assertOk()
            ->assertJsonPath('data.is_voided', true)
            ->assertJsonPath('data.void_reason', 'Incorrect amount entered')
            ->assertJsonPath('data.voided_by', $this->finance->name);

        $gift = Contribution::query()->find($this->gift->id);
        expect($gift)->not->toBeNull()->and($gift->is_voided)->toBeTrue()->and($gift->voided_by)->toBe($this->finance->id)->and($gift->voided_at)->not->toBeNull();
    });

    it('needs a reason', function (array $body): void {
        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', $body)->assertUnprocessable()->assertJsonValidationErrors('reason');

        expect($this->gift->fresh()->is_voided)->toBeFalse();
    })->with([[[]], [['reason' => '']], [['reason' => 'ab']], [['reason' => str_repeat('a', 2001)]]]);

    it('cannot be done twice (TC-06-12)', function (): void {
        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'First reason'])->assertOk();

        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'Second reason'])
            ->assertUnprocessable()->assertJsonPath('message', 'This contribution has already been voided.');
        expect($this->gift->fresh()->void_reason)->toBe('First reason');
    });

    it('writes a change-history line with the reason', function (): void {
        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'Member asked for it to be reversed'])->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->gift->id)->where('event', AuditEvent::Voided)->sole();
        expect($line->reason)->toBe('Member asked for it to be reversed')->and($line->actor_id)->toBe($this->finance->id);
    });

    it('drops out of the member\'s total but stays visible when asked for', function (): void {
        $total = fn () => app(ContributionRepositoryInterface::class)->totalForMember($this->member->id, null);
        expect($total())->toBe('200.00');

        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'Entered twice'])->assertOk();

        expect($total())->toBe('0.00')
            ->and($this->getJson(GIFTS.'/'.$this->gift->id)->assertOk()->json('data.is_voided'))->toBeTrue();
    });

    it('has no way to delete a gift (TC-06-13)', function (): void {
        $this->deleteJson(GIFTS.'/'.$this->gift->id)->assertStatus(405);

        expect(Contribution::query()->find($this->gift->id))->not->toBeNull();
    });
});

describe('authentication and roles (QA-10)', function (): void {
    beforeEach(fn () => $this->gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id]));

    it('rejects every endpoint without a token', function (string $method, string $path): void {
        $this->app['auth']->forgetGuards();

        $this->json($method, str_replace('{id}', $this->gift->id, GIFTS.$path))->assertUnauthorized();
    })->with([['GET', ''], ['POST', ''], ['GET', '/{id}'], ['PATCH', '/{id}/void']]);

    it('lets only the church admin and finance secretary see, record and void gifts', function (string $role, bool $allowed): void {
        Sanctum::actingAs(giftStaff($this->church, $role), ['*']);

        $this->getJson(GIFTS)->assertStatus($allowed ? 200 : 403);
        $this->getJson(GIFTS.'/'.$this->gift->id)->assertStatus($allowed ? 200 : 403);
        recordGift()->assertStatus($allowed ? 201 : 403);
        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'Test void'])->assertStatus($allowed ? 200 : 403);
    })->with([['church-admin', true], ['finance-secretary', true], ['pastor', false], ['department-head', false], ['youth-pastor', false]]);

    it('keeps seeing, recording and voiding apart, so one can be given without the others (D-046)', function (): void {
        $viewer = User::factory()->for($this->church)->create();
        $viewer->givePermissionTo('giving.view');
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson(GIFTS)->assertOk();
        recordGift()->assertForbidden();
        $this->patchJson(GIFTS.'/'.$this->gift->id.'/void', ['reason' => 'Test void'])->assertForbidden();
    });
});

describe('another church (isolation, QA-02)', function (): void {
    it('is a 404 to read or void a gift of another church, and nothing changes', function (): void {
        $stranger = Contribution::factory()->create(['church_id' => $this->other->id]);

        $this->getJson(GIFTS.'/'.$stranger->id)->assertNotFound();
        $this->patchJson(GIFTS.'/'.$stranger->id.'/void', ['reason' => 'Not yours'])->assertNotFound();
        expect(Contribution::query()->withoutGlobalScopes()->find($stranger->id)->is_voided)->toBeFalse();
    });
});
