<?php

declare(strict_types=1);

use App\Enums\ConsentPurpose;
use App\Models\Church;
use App\Models\Consent;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Consent history (agreement section 36): every time a member agrees to receive texts or WhatsApp messages it is
// recorded with the time and the policy version, and taking it back is recorded too.

const CONSENT_MEMBERS = 'http://grace.localhost/api/v1/members';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'member_number_prefix' => 'GRC']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

/** A valid new member body, with the message choices given. */
function newMemberBody(array $choices = []): array
{
    return ['first_name' => 'Abena', 'last_name' => 'Asante', 'gender' => 'female', 'phone' => '0200987654', ...$choices];
}

it('records an agreement for each message choice a new member starts with', function (): void {
    $response = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true, 'whatsapp_opted_in' => true]))->assertCreated();

    $consents = Consent::query()->where('member_id', $response->json('data.id'))->get();
    expect($consents->pluck('purpose')->all())->toEqualCanonicalizing([ConsentPurpose::Sms, ConsentPurpose::Whatsapp])
        ->and($consents->every(fn (Consent $c): bool => $c->withdrawn_at === null && $c->policy_version === '1.0'))->toBeTrue();
});

it('records nothing for a choice that is off', function (): void {
    $response = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => false, 'whatsapp_opted_in' => false]))->assertCreated();

    expect(Consent::query()->where('member_id', $response->json('data.id'))->count())->toBe(0);
});

it('marks the agreement withdrawn when the choice is switched off', function (): void {
    $id = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true, 'whatsapp_opted_in' => false]))->json('data.id');

    $this->putJson(CONSENT_MEMBERS.'/'.$id, ['sms_opted_in' => false])->assertOk();

    $consent = Consent::query()->where('member_id', $id)->sole();
    expect($consent->withdrawn_at)->not->toBeNull();
});

it('keeps the old agreement and adds a new row when the choice is switched on again', function (): void {
    $id = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true]))->json('data.id');
    $this->putJson(CONSENT_MEMBERS.'/'.$id, ['sms_opted_in' => false])->assertOk();

    $this->putJson(CONSENT_MEMBERS.'/'.$id, ['sms_opted_in' => true])->assertOk();

    $rows = Consent::query()->where('member_id', $id)->where('purpose', ConsentPurpose::Sms)->orderBy('consented_at')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->withdrawn_at)->not->toBeNull()
        ->and($rows[1]->withdrawn_at)->toBeNull();
});

it('does not add a row when an update leaves the choice as it was', function (): void {
    $id = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true]))->json('data.id');

    $this->putJson(CONSENT_MEMBERS.'/'.$id, ['occupation' => 'Nurse'])->assertOk();

    expect(Consent::query()->where('member_id', $id)->count())->toBe(1);
});

it('records who recorded the agreement', function (): void {
    $id = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true]))->json('data.id');

    expect(Consent::query()->where('member_id', $id)->sole()->recorded_by)->toBe($this->admin->id);
});

it('lets the database refuse a second open agreement for the same purpose', function (): void {
    $member = Member::factory()->create(['church_id' => $this->church->id]);
    Consent::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'purpose' => ConsentPurpose::Sms]);

    // The refused insert runs in its own savepoint, so the test's transaction survives it.
    expect(fn () => DB::transaction(fn () => Consent::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'purpose' => ConsentPurpose::Sms])))
        ->toThrow(QueryException::class);
    // A withdrawn one does not count, and another purpose is separate.
    Consent::factory()->withdrawn()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'purpose' => ConsentPurpose::Sms]);
    Consent::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'purpose' => ConsentPurpose::Whatsapp]);
    expect(Consent::query()->where('member_id', $member->id)->count())->toBe(3);
});

it('withdraws every agreement when the member is erased', function (): void {
    $id = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true, 'whatsapp_opted_in' => true]))->json('data.id');

    $this->postJson(CONSENT_MEMBERS.'/'.$id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

    expect(Consent::query()->where('member_id', $id)->whereNull('withdrawn_at')->count())->toBe(0)
        ->and(Consent::query()->where('member_id', $id)->count())->toBe(2);
});

it('lists the consent history in the member\'s data export', function (): void {
    $id = $this->postJson(CONSENT_MEMBERS, newMemberBody(['sms_opted_in' => true]))->json('data.id');
    $this->putJson(CONSENT_MEMBERS.'/'.$id, ['sms_opted_in' => false])->assertOk();

    $consents = $this->getJson(CONSENT_MEMBERS.'/'.$id.'/data-export')->assertOk()->json('consents');

    expect($consents)->toHaveCount(1)
        ->and($consents[0])->toMatchArray(['purpose' => 'sms', 'policy_version' => '1.0'])
        ->and($consents[0]['withdrawn_at'])->not->toBeNull();
});

it('never shows another church\'s consents', function (): void {
    $other = Church::factory()->create(['slug' => 'other']);
    $stranger = Member::factory()->create(['church_id' => $other->id]);
    Consent::factory()->create(['church_id' => $other->id, 'member_id' => $stranger->id]);
    app()->instance('current.church', $this->church);

    expect(Consent::query()->count())->toBe(0)
        ->and(Consent::query()->withoutGlobalScopes()->count())->toBe(1);
});
