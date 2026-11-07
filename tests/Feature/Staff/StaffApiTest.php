<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\EmailCodePurpose;
use App\Enums\StaffPermission;
use App\Jobs\SendEmailJob;
use App\Mail\EmailCodeMail;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Staff management, part 1 (D-055 point 7, docs/08 Staff, QA-10): list, invite and change name, phone and role. Everything
// here needs `staff.manage`, which only the church admin holds. A staff member of another church is a 404.

const STAFF = 'http://grace.localhost/api/v1/staff';

/** A staff member of the church with one role. */
function staffOf(Church $church, string $role, array $attributes = []): User
{
    $user = User::factory()->for($church)->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** Invites someone with a fresh Idempotency-Key unless one is given. */
function inviteStaff(array $body = [], ?string $key = null): TestResponse
{
    $body = [...['name' => 'Akosua Mensah', 'email' => 'Akosua@Example.com', 'phone' => '0244123456', 'role' => 'pastor'], ...$body];

    return test()->postJson(STAFF, $body, ['Idempotency-Key' => $key ?? 'staff-'.Str::random(20)]);
}

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->admin = staffOf($this->church, 'church-admin', ['name' => 'Kofi Admin']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs($this->admin, ['*']);
});

describe('listing', function (): void {
    it('lists this church\'s staff by name, active or not, with their roles and extra permissions', function (): void {
        $pastor = staffOf($this->church, 'pastor', ['name' => 'Abena Pastor']);
        $pastor->givePermissionTo('members.export');
        staffOf($this->church, 'finance-secretary', ['name' => 'Zeta Finance', 'is_active' => false]);

        $rows = $this->getJson(STAFF)->assertOk()->json('data');

        expect(collect($rows)->pluck('name')->all())->toBe(['Abena Pastor', 'Kofi Admin', 'Zeta Finance']);
        expect($rows[0])->toMatchArray(['id' => $pastor->id, 'email' => $pastor->email, 'phone' => $pastor->phone, 'roles' => ['pastor'], 'extra_permissions' => ['members.export'], 'is_active' => true])
            ->and($rows[0])->toHaveKey('last_login_at')
            ->and($rows[2]['is_active'])->toBeFalse();
    });

    it('says which permissions each person\'s role already gives, apart from the extras, so a screen can tell them apart', function (): void {
        $pastor = staffOf($this->church, 'pastor', ['name' => 'Abena Pastor']);
        $pastor->givePermissionTo('members.export');

        $rows = collect($this->getJson(STAFF)->assertOk()->json('data'))->keyBy('name');

        // The pastor's role gives these six; the extra one is listed apart and is not among them.
        expect($rows['Abena Pastor']['role_permissions'])->toEqualCanonicalizing(['members.view', 'members.edit', 'milestones.view', 'giving.summary', 'reports.view', 'sms.send'])
            ->and($rows['Abena Pastor']['extra_permissions'])->toBe(['members.export'])
            // The church admin carries every permission there is.
            ->and($rows['Kofi Admin']['role_permissions'])->toHaveCount(count(StaffPermission::cases()));
    });

    it('never shows a password, and never another church\'s staff (isolation)', function (): void {
        staffOf($this->other, 'church-admin', ['name' => 'Someone Else']);

        $response = $this->getJson(STAFF)->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->getContent())->not->toContain('Someone Else')->not->toContain('password');
    });

    it('is paged', function (): void {
        staffOf($this->church, 'pastor');
        staffOf($this->church, 'pastor');

        $response = $this->getJson(STAFF.'?per_page=2')->assertOk();

        expect($response->json('data'))->toHaveCount(2)->and($response->json('meta.total'))->toBe(3);
        $this->getJson(STAFF.'?per_page=1000')->assertStatus(422)->assertJsonValidationErrors('per_page');
    });
});

describe('inviting', function (): void {
    it('creates the staff member with no password, in lowercase email and international phone form', function (): void {
        $response = inviteStaff()->assertCreated();

        $user = User::query()->where('email', 'akosua@example.com')->sole();
        expect($response->json('data'))->toMatchArray(['id' => $user->id, 'name' => 'Akosua Mensah', 'email' => 'akosua@example.com', 'phone' => '+233244123456', 'roles' => ['pastor'], 'is_active' => true])
            ->and($user->password)->toBeNull()
            ->and($user->church_id)->toBe($this->church->id)
            ->and($user->hasRole('pastor'))->toBeTrue()
            ->and($user->email_verified_at)->toBeNull();
    });

    it('emails an invitation code that lets the person choose their password', function (): void {
        inviteStaff()->assertCreated();

        Queue::assertPushedOn('critical', SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === 'akosua@example.com' && $job->email->messageType === 'staff_invitation_code');
        $mail = Queue::pushed(SendEmailJob::class)->sole()->email->mailable;
        expect($mail)->toBeInstanceOf(EmailCodeMail::class)->and($mail->purpose)->toBe(EmailCodePurpose::StaffInvitation);
        $mail->assertHasSubject('Your invitation code for Grace Assembly');

        // The code is for the reset flow: it sets the first password, and the person can then sign in.
        $this->postJson('http://grace.localhost/api/v1/auth/password/reset', [
            'email' => 'akosua@example.com', 'code' => $mail->code, 'password' => 'a long passphrase here', 'password_confirmation' => 'a long passphrase here',
        ])->assertNoContent();
        expect(User::query()->where('email', 'akosua@example.com')->sole()->password)->not->toBeNull();
    });

    it('writes the invitation to the change history', function (): void {
        $id = inviteStaff()->assertCreated()->json('data.id');

        $line = AuditLog::query()->where('auditable_id', $id)->sole();
        expect($line->event)->toBe(AuditEvent::Created)->and($line->actor_id)->toBe($this->admin->id)
            ->and($line->new_values)->toMatchArray(['name' => 'Akosua Mensah', 'email' => 'akosua@example.com', 'role' => 'pastor'])
            ->and(json_encode($line->new_values))->not->toContain('password');
    });

    it('does nothing twice when the same Idempotency-Key is sent again', function (): void {
        inviteStaff([], 'same-staff-key')->assertCreated();
        inviteStaff([], 'same-staff-key')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect(User::query()->where('email', 'akosua@example.com')->count())->toBe(1);
        Queue::assertPushed(SendEmailJob::class, 1);
    });

    it('needs an Idempotency-Key', function (): void {
        $this->postJson(STAFF, ['name' => 'A', 'email' => 'a@example.com', 'phone' => '0244123456', 'role' => 'pastor'])->assertStatus(400);
    });

    it('asks for every field', function (): void {
        $this->postJson(STAFF, [], ['Idempotency-Key' => 'staff-empty-body'])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'phone', 'role']);
    });

    it('refuses a role that does not exist, and a bad email or phone', function (): void {
        inviteStaff(['role' => 'super-admin'])->assertStatus(422)->assertJsonValidationErrors('role');
        inviteStaff(['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
        inviteStaff(['phone' => '12'])->assertStatus(422)->assertJsonValidationErrors('phone');
        expect(User::query()->count())->toBe(1);
    });

    it('refuses an email or phone already used by staff of this church, whatever the capitals', function (): void {
        staffOf($this->church, 'pastor', ['email' => 'akosua@example.com', 'phone' => '+233244123456']);

        inviteStaff(['email' => 'AKOSUA@example.com', 'phone' => '0200000001'])->assertStatus(422)->assertJsonValidationErrors('email');
        inviteStaff(['email' => 'new@example.com'])->assertStatus(422)->assertJsonValidationErrors('phone');
    });

    it('allows an email used in another church, and one that belonged to deleted staff', function (): void {
        staffOf($this->other, 'pastor', ['email' => 'akosua@example.com', 'phone' => '+233244123456']);
        staffOf($this->church, 'pastor', ['email' => 'gone@example.com', 'phone' => '+233200000009'])->delete();

        inviteStaff()->assertCreated();
        inviteStaff(['email' => 'gone@example.com', 'phone' => '0200000002'])->assertCreated();
    });

    it('is limited, because every invitation sends an email', function (): void {
        config(['limits.staff_invites_per_hour' => 2]);

        inviteStaff(['email' => 'one@example.com', 'phone' => '0200000011'])->assertCreated();
        inviteStaff(['email' => 'two@example.com', 'phone' => '0200000012'])->assertCreated();
        inviteStaff(['email' => 'three@example.com', 'phone' => '0200000013'])->assertStatus(429)->assertHeader('Retry-After');
    });
});

describe('changing name, phone and role', function (): void {
    beforeEach(function (): void {
        $this->pastor = staffOf($this->church, 'pastor', ['name' => 'Abena Pastor', 'phone' => '+233244000001']);
    });

    it('changes the fields it is given and leaves the rest', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id, ['name' => 'Abena Owusu'])->assertOk()->assertJsonPath('data.name', 'Abena Owusu');

        $this->pastor->refresh();
        expect($this->pastor->name)->toBe('Abena Owusu')->and($this->pastor->phone)->toBe('+233244000001')->and($this->pastor->hasRole('pastor'))->toBeTrue();
    });

    it('changes the phone to international form and the role at once', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id, ['phone' => '0200111222', 'role' => 'finance-secretary'])->assertOk()
            ->assertJsonPath('data.phone', '+233200111222')->assertJsonPath('data.roles', ['finance-secretary']);

        $this->pastor->refresh();
        expect($this->pastor->hasRole('finance-secretary'))->toBeTrue()->and($this->pastor->hasRole('pastor'))->toBeFalse()
            ->and($this->pastor->can('giving.record'))->toBeTrue()->and($this->pastor->can('sms.send'))->toBeFalse();
    });

    it('does not change the email', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id, ['name' => 'Abena Owusu', 'email' => 'taken-over@example.com'])->assertOk();

        expect($this->pastor->fresh()->email)->toBe($this->pastor->email);
    });

    it('keeps a deactivated person deactivated', function (): void {
        $this->pastor->update(['is_active' => false]);

        $this->putJson(STAFF.'/'.$this->pastor->id, ['name' => 'Abena Owusu'])->assertOk()->assertJsonPath('data.is_active', false);
    });

    it('writes the old and new values to the change history', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id, ['name' => 'Abena Owusu', 'role' => 'youth-pastor'])->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->pastor->id)->sole();
        expect($line->event)->toBe(AuditEvent::Updated)->and($line->actor_id)->toBe($this->admin->id)
            ->and($line->old_values)->toBe(['name' => 'Abena Pastor', 'role' => 'pastor'])
            ->and($line->new_values)->toBe(['name' => 'Abena Owusu', 'role' => 'youth-pastor']);
    });

    it('writes nothing to the history when nothing changed', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id, ['name' => 'Abena Pastor', 'role' => 'pastor'])->assertOk();

        expect(AuditLog::query()->where('auditable_id', $this->pastor->id)->count())->toBe(0);
    });

    it('refuses a bad role, a bad phone, a phone already in use and an empty name', function (): void {
        staffOf($this->church, 'pastor', ['phone' => '+233200999999']);

        $this->putJson(STAFF.'/'.$this->pastor->id, ['role' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('role');
        $this->putJson(STAFF.'/'.$this->pastor->id, ['phone' => '12'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->putJson(STAFF.'/'.$this->pastor->id, ['phone' => '0200999999'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->putJson(STAFF.'/'.$this->pastor->id, ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
    });

    it('lets a person keep their own phone number', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id, ['phone' => '0244000001', 'name' => 'Abena Owusu'])->assertOk();
    });
});

describe('the last church admin', function (): void {
    it('cannot lose the church admin role', function (): void {
        $this->putJson(STAFF.'/'.$this->admin->id, ['role' => 'pastor'])->assertStatus(409)
            ->assertJsonPath('message', 'A church needs at least one active church admin.');

        expect($this->admin->fresh()->hasRole('church-admin'))->toBeTrue();
    });

    it('can lose the role when another active church admin remains', function (): void {
        staffOf($this->church, 'church-admin');

        $this->putJson(STAFF.'/'.$this->admin->id, ['role' => 'pastor'])->assertOk();
    });

    it('does not count a deactivated church admin as one who remains', function (): void {
        staffOf($this->church, 'church-admin', ['is_active' => false]);

        $this->putJson(STAFF.'/'.$this->admin->id, ['role' => 'pastor'])->assertStatus(409);
    });

    it('does not count a church admin of another church', function (): void {
        staffOf($this->other, 'church-admin');

        $this->putJson(STAFF.'/'.$this->admin->id, ['role' => 'pastor'])->assertStatus(409);
    });

    it('is fine to send the church admin role again', function (): void {
        $this->putJson(STAFF.'/'.$this->admin->id, ['role' => 'church-admin', 'name' => 'Kofi Boateng'])->assertOk();
    });
});

describe('who may manage staff', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(STAFF)->assertUnauthorized();
        $this->postJson(STAFF, [])->assertUnauthorized();
        $this->putJson(STAFF.'/'.$this->admin->id, [])->assertUnauthorized();
    });

    it('refuses every role except the church admin', function (string $role): void {
        $someone = staffOf($this->church, $role);
        Sanctum::actingAs($someone, ['*']);

        $this->getJson(STAFF)->assertForbidden();
        inviteStaff()->assertForbidden();
        $this->putJson(STAFF.'/'.$this->admin->id, ['name' => 'X'])->assertForbidden();
        expect(User::query()->count())->toBe(2);
    })->with(['pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('treats a staff member of another church as not found (isolation)', function (): void {
        $stranger = staffOf($this->other, 'pastor', ['name' => 'Stranger']);

        $this->putJson(STAFF.'/'.$stranger->id, ['name' => 'Changed'])->assertNotFound();
        expect($stranger->fresh()->name)->toBe('Stranger');
    });
});
