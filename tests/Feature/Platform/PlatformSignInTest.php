<?php

declare(strict_types=1);

use App\Jobs\SendEmailJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Models\PlatformAdmin;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// The platform admin side (D-069): the owner's own accounts, apart from every church. They sign in on the platform host with
// email, password and an emailed code, as church staff do (D-054), with their own codes, limits and sessions. A church's
// staff can never reach this side, and these accounts can never reach a church.

const PLATFORM_API = 'http://localhost/api/v1/platform';
const PLATFORM_PASSWORD = 'a long platform passphrase';

beforeEach(function (): void {
    Queue::fake();
    $this->admin = PlatformAdmin::factory()->create(['email' => 'owner@platform.test', 'name' => 'Platform Owner', 'password' => PLATFORM_PASSWORD]);
});

/** Step 1: email and password. */
function platformPassword(array $body = []): TestResponse
{
    return test()->postJson(PLATFORM_API.'/auth/login', [...['email' => 'owner@platform.test', 'password' => PLATFORM_PASSWORD], ...$body]);
}

/** Step 2: the emailed code, from the platform's own page so the browser is signed in by cookie. */
function platformCode(string $code, array $body = []): TestResponse
{
    return test()->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json'])
        ->postJson(PLATFORM_API.'/auth/login/verify', [...['email' => 'owner@platform.test', 'code' => $code], ...$body]);
}

/** The code that was put in the queued email. */
function platformEmailedCode(): string
{
    return Queue::pushed(SendEmailJob::class)->last()->email->mailable->code;
}

describe('step 1: the password', function (): void {
    it('emails a code when the password is right, and says nothing else', function (): void {
        platformPassword()->assertOk()->assertExactJson(['message' => 'Code sent', 'expires_in' => 600]);

        Queue::assertPushedOn('critical', SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === 'owner@platform.test' && $job->email->churchId === null);
        expect(platformEmailedCode())->toMatch('/^\d{6}$/');
    });

    it('records the email in the message log without the code', function (): void {
        platformPassword()->assertOk();

        $log = MessageLog::query()->withoutGlobalScopes()->whereNull('church_id')->sole();
        expect($log->recipient_email)->toBe('owner@platform.test')->and($log->content)->not->toContain(platformEmailedCode());
    });

    it('gives one answer for a wrong password, an unknown email and a deactivated account, and sends nothing', function (array $body, ?callable $arrange): void {
        if ($arrange !== null) {
            $arrange($this->admin);
        }

        platformPassword($body)->assertUnprocessable()->assertExactJson(['message' => 'Invalid email or password']);

        Queue::assertNothingPushed();
    })->with([
        'wrong password' => [['password' => 'not the passphrase!!'], null],
        'unknown email' => [['email' => 'nobody@platform.test'], null],
        'deactivated account' => [[], fn (PlatformAdmin $admin) => $admin->update(['is_active' => false])],
    ]);

    it('locks the email for a while after five wrong passwords, even for the right one', function (): void {
        foreach (range(1, 4) as $try) {
            platformPassword(['password' => "wrong passphrase {$try}"])->assertUnprocessable();
        }

        platformPassword(['password' => 'wrong passphrase 5'])->assertStatus(429);
        platformPassword()->assertStatus(429);
        Queue::assertNothingPushed();
    });

    it('limits how often a code can be asked for: three a minute for one email', function (): void {
        foreach (range(1, 3) as $ignored) {
            platformPassword()->assertOk();
        }

        platformPassword()->assertStatus(429)->assertHeader('Retry-After');
    });

    it('checks the email and password are sent, and the email is an email', function (): void {
        platformPassword(['email' => 'not an email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        platformPassword(['password' => ''])->assertUnprocessable()->assertJsonValidationErrors('password');
    });

    it('does not exist on a church\'s address', function (): void {
        $church = Church::factory()->create(['slug' => 'grace']);

        $this->postJson('http://grace.localhost/api/v1/platform/auth/login', ['email' => 'owner@platform.test', 'password' => PLATFORM_PASSWORD])->assertNotFound();
        expect($church->exists)->toBeTrue();
    });
});

describe('step 2: the code', function (): void {
    beforeEach(function (): void {
        platformPassword()->assertOk();
        $this->code = platformEmailedCode();
    });

    it('signs the owner in with a cookie and no token, and stamps the time', function (): void {
        $response = platformCode($this->code)->assertOk();

        expect($response->json('data'))->toMatchArray(['id' => $this->admin->id, 'name' => 'Platform Owner', 'email' => 'owner@platform.test'])
            ->and($response->json())->not->toHaveKey('token')->and($response->getContent())->not->toContain('password')
            ->and($this->admin->fresh()->last_login_at)->not->toBeNull();
    });

    it('lets the signed-in owner read who they are, and sign out', function (): void {
        platformCode($this->code)->assertOk();

        $this->getJson(PLATFORM_API.'/auth/me')->assertOk()->assertJsonPath('data.email', 'owner@platform.test');

        $this->postJson(PLATFORM_API.'/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->getJson(PLATFORM_API.'/auth/me')->assertUnauthorized();
    });

    it('uses a code once', function (): void {
        platformCode($this->code)->assertOk();

        platformCode($this->code)->assertUnprocessable();
    });

    it('refuses a wrong code, and locks after five wrong ones', function (): void {
        foreach (range(1, 4) as $try) {
            platformCode('000000')->assertUnprocessable();
        }

        platformCode('000000')->assertStatus(429);
        platformCode($this->code)->assertStatus(429);
    });

    it('answers the same for a code sent to another email, or an account that is switched off', function (): void {
        platformCode($this->code, ['email' => 'nobody@platform.test'])->assertUnprocessable();

        $this->admin->update(['is_active' => false]);
        platformCode($this->code)->assertUnprocessable();
    });

    it('does not accept a church staff member\'s sign-in code for the same email', function (): void {
        // A church's code is kept under that church's own key, so it can never open the platform side.
        $church = Church::factory()->create(['slug' => 'grace']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->for($church)->create(['email' => 'owner@platform.test']);
        $staff->forceFill(['password' => 'a long passphrase here'])->save();
        Cache::flush();
        $this->postJson('http://grace.localhost/api/v1/auth/login', ['email' => 'owner@platform.test', 'password' => 'a long passphrase here'])->assertOk();
        $churchCode = Queue::pushed(SendEmailJob::class)->last()->email->mailable->code;

        platformCode($churchCode)->assertUnprocessable();
    });
});

describe('the two sides stay apart', function (): void {
    it('keeps a church staff member out of the platform side', function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        $church = Church::factory()->create(['slug' => 'grace']);
        $staff = User::factory()->for($church)->create();
        $staff->assignRole('church-admin');
        Sanctum::actingAs($staff, ['*']);

        $this->getJson(PLATFORM_API.'/auth/me')->assertUnauthorized();
    });

    it('keeps a platform admin out of a church, who has no church and no permissions there', function (): void {
        $church = Church::factory()->create(['slug' => 'grace']);
        $this->actingAs($this->admin, 'platform');

        $this->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('http://grace.localhost/api/v1/members')->assertUnauthorized();
        expect($church->exists)->toBeTrue();
    });

    it('refuses the platform side to someone who is not signed in', function (): void {
        $this->getJson(PLATFORM_API.'/auth/me')->assertUnauthorized();
        $this->postJson(PLATFORM_API.'/auth/logout')->assertUnauthorized();
    });
});

describe('creating an account from the command line', function (): void {
    it('creates an owner account with a hashed password, and no sign-up exists', function (): void {
        $this->artisan('platform:create-admin', ['email' => 'New.Owner@Platform.test', 'name' => 'New Owner'])
            ->expectsQuestion('Password (at least 12 characters)', 'another long passphrase')
            ->expectsQuestion('Confirm the password', 'another long passphrase')
            ->assertSuccessful();

        $admin = PlatformAdmin::query()->where('email', 'new.owner@platform.test')->sole();
        expect($admin->name)->toBe('New Owner')->and($admin->is_active)->toBeTrue()->and($admin->password)->not->toBe('another long passphrase')
            ->and(password_verify('another long passphrase', $admin->password))->toBeTrue();
    });

    it('refuses a short password, a password that does not match, and an email already in use', function (): void {
        $this->artisan('platform:create-admin', ['email' => 'short@platform.test', 'name' => 'Short'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm the password', 'short')
            ->assertFailed();

        $this->artisan('platform:create-admin', ['email' => 'mismatch@platform.test', 'name' => 'Mismatch'])
            ->expectsQuestion('Password (at least 12 characters)', 'a long enough passphrase')
            ->expectsQuestion('Confirm the password', 'a different passphrase!')
            ->assertFailed();

        $this->artisan('platform:create-admin', ['email' => 'owner@platform.test', 'name' => 'Again'])->assertFailed();

        expect(PlatformAdmin::query()->count())->toBe(1);
    });
});
