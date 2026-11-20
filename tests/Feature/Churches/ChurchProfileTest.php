<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// The church's own details (docs/02 "Configure church profile", docs/08 Church Profile, QA-10, D-055 point 6): name, denomination,
// address, city, region, phone and email. Any staff member may read them; only the church admin (`church.settings`) changes them,
// and every change is written to the history. The web address, plan, credits and the rest are not editable here.

const CHURCH_PROFILE = 'http://grace.localhost/api/v1/church';

/** A staff member of the church with one role. */
function profileStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create([
        'slug' => 'grace', 'name' => 'Grace Assembly', 'denomination' => 'Pentecostal', 'address' => '12 Ring Road', 'city' => 'Kumasi', 'region' => 'Ashanti',
        'phone' => '+233302000000', 'email' => 'office@grace.example', 'timezone' => 'Africa/Accra',
    ]);
    $this->other = Church::factory()->create(['slug' => 'other', 'name' => 'Other Church', 'city' => 'Accra']);
    $this->admin = profileStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('reading', function (): void {
    it('gives the church\'s details', function (): void {
        $this->getJson(CHURCH_PROFILE)->assertOk()->assertExactJson(['data' => [
            'id' => $this->church->id, 'name' => 'Grace Assembly', 'slug' => 'grace', 'denomination' => 'Pentecostal', 'address' => '12 Ring Road', 'city' => 'Kumasi',
            'region' => 'Ashanti', 'phone' => '+233302000000', 'email' => 'office@grace.example', 'timezone' => 'Africa/Accra',
        ]]);
    });

    it('shows a detail the church never gave as null', function (): void {
        $this->church->update(['denomination' => null, 'address' => null, 'phone' => null, 'email' => null]);

        $data = $this->getJson(CHURCH_PROFILE)->assertOk()->json('data');

        expect($data['denomination'])->toBeNull()->and($data['address'])->toBeNull()->and($data['phone'])->toBeNull()->and($data['email'])->toBeNull();
    });

    it('never shows payout, billing or credit details', function (): void {
        $this->church->update(['payout_account_number' => '0244000000', 'payout_account_name' => 'Secret Holder', 'sms_credits' => 777]);

        $body = $this->getJson(CHURCH_PROFILE)->assertOk()->getContent();

        expect($body)->not->toContain('0244000000')->not->toContain('Secret Holder')->not->toContain('777')->not->toContain('payout')->not->toContain('plan');
    });

    it('is the signed-in church\'s own, never another church\'s (isolation)', function (): void {
        expect($this->getJson(CHURCH_PROFILE)->assertOk()->json('data.name'))->toBe('Grace Assembly');
    });
});

describe('changing', function (): void {
    it('saves what is sent and answers with the whole profile', function (): void {
        $this->putJson(CHURCH_PROFILE, ['name' => 'Grace Assembly International', 'city' => 'Tema'])->assertOk()
            ->assertJsonPath('data.name', 'Grace Assembly International')->assertJsonPath('data.city', 'Tema')->assertJsonPath('data.region', 'Ashanti');

        $church = $this->church->fresh();
        expect($church->name)->toBe('Grace Assembly International')->and($church->city)->toBe('Tema')->and($church->address)->toBe('12 Ring Road');
    });

    it('puts the phone into international form and the email into lowercase', function (): void {
        $this->putJson(CHURCH_PROFILE, ['phone' => '0302111222', 'email' => 'Office@Grace.Example'])->assertOk()
            ->assertJsonPath('data.phone', '+233302111222')->assertJsonPath('data.email', 'office@grace.example');
    });

    it('lets an optional detail be cleared, but not the name', function (): void {
        $this->putJson(CHURCH_PROFILE, ['denomination' => null, 'address' => '', 'phone' => null])->assertOk()
            ->assertJsonPath('data.denomination', null)->assertJsonPath('data.address', null)->assertJsonPath('data.phone', null);

        $this->putJson(CHURCH_PROFILE, ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson(CHURCH_PROFILE, ['name' => null])->assertStatus(422)->assertJsonValidationErrors('name');
        expect($this->church->fresh()->name)->toBe('Grace Assembly');
    });

    it('does not touch another church', function (): void {
        $this->putJson(CHURCH_PROFILE, ['name' => 'Renamed', 'city' => 'Tema'])->assertOk();

        expect($this->other->fresh()->name)->toBe('Other Church')->and($this->other->fresh()->city)->toBe('Accra');
    });

    it('writes the old and new values to the history, and nothing when nothing changed', function (): void {
        $this->putJson(CHURCH_PROFILE, ['name' => 'Grace Assembly International', 'city' => 'Kumasi'])->assertOk();
        $this->putJson(CHURCH_PROFILE, ['name' => 'Grace Assembly International', 'city' => 'Kumasi'])->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->church->id)->sole();
        expect($line->event)->toBe(AuditEvent::Updated)->and($line->actor_id)->toBe($this->admin->id)
            ->and($line->old_values)->toBe(['name' => 'Grace Assembly'])->and($line->new_values)->toBe(['name' => 'Grace Assembly International']);
    });

    it('refuses the details that are not the church\'s to change here', function (string $field, mixed $value): void {
        $this->putJson(CHURCH_PROFILE, ['name' => 'Fine', $field => $value])->assertStatus(422)->assertJsonValidationErrors($field);

        $church = $this->church->fresh();
        expect($church->name)->toBe('Grace Assembly')->and($church->slug)->toBe('grace');
    })->with([
        'web address' => ['slug', 'stolen-name'],
        'time zone' => ['timezone', 'Europe/London'],
        'member number letters' => ['member_number_prefix', 'ZZZ'],
        'plan' => ['plan', 'enterprise'],
        'credits' => ['sms_credits', 99999],
    ]);
});

describe('validation', function (): void {
    it('asks for at least one detail', function (): void {
        $this->putJson(CHURCH_PROFILE, [])->assertStatus(422)->assertJsonValidationErrors('profile');
    });

    it('checks each detail', function (): void {
        $this->putJson(CHURCH_PROFILE, ['name' => str_repeat('a', 256)])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson(CHURCH_PROFILE, ['denomination' => str_repeat('a', 256)])->assertStatus(422)->assertJsonValidationErrors('denomination');
        $this->putJson(CHURCH_PROFILE, ['address' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors('address');
        $this->putJson(CHURCH_PROFILE, ['city' => str_repeat('a', 101)])->assertStatus(422)->assertJsonValidationErrors('city');
        $this->putJson(CHURCH_PROFILE, ['region' => str_repeat('a', 101)])->assertStatus(422)->assertJsonValidationErrors('region');
        $this->putJson(CHURCH_PROFILE, ['phone' => '12'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->putJson(CHURCH_PROFILE, ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
    });

    it('keeps text as text: no markup is stored as sent', function (): void {
        $this->putJson(CHURCH_PROFILE, ['name' => '<script>alert(1)</script> Church'])->assertOk()->assertJsonPath('data.name', '<script>alert(1)</script> Church');
    });
});

describe('who may', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(CHURCH_PROFILE)->assertUnauthorized();
        $this->putJson(CHURCH_PROFILE, ['name' => 'X'])->assertUnauthorized();
    });

    it('lets every role read the details', function (string $role): void {
        Sanctum::actingAs(profileStaff($this->church, $role), ['*']);

        $this->getJson(CHURCH_PROFILE)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('lets only the church admin change them', function (string $role): void {
        Sanctum::actingAs(profileStaff($this->church, $role), ['*']);

        $this->putJson(CHURCH_PROFILE, ['name' => 'Hijacked'])->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
        expect($this->church->fresh()->name)->toBe('Grace Assembly');
    })->with(['pastor', 'finance-secretary', 'department-head', 'youth-pastor']);
});
