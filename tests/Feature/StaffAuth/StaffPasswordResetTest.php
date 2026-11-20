<?php

declare(strict_types=1);

use App\Enums\EmailCodePurpose;
use App\Jobs\CheckPasswordBreachJob;
use App\Jobs\SendEmailJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

// D-054: a forgotten password, and the first password of a staff member who has none, are set with an emailed code.

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->user = User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);
    $this->user->forceFill(['password' => 'the old passphrase here'])->save();
    $this->user->assignRole('church-admin');
});

/** Asks for a reset code. */
function staffForgot(array $body = [], string $slug = 'grace'): TestResponse
{
    return test()->postJson("http://{$slug}.localhost/api/v1/auth/password/forgot", [...['email' => 'akosua@example.com'], ...$body]);
}

/** Sets the new password with the emailed code. */
function staffReset(string $code, array $body = [], string $slug = 'grace'): TestResponse
{
    $password = 'a brand new passphrase';

    return test()->postJson("http://{$slug}.localhost/api/v1/auth/password/reset", [
        ...['email' => 'akosua@example.com', 'code' => $code, 'password' => $password, 'password_confirmation' => $password],
        ...$body,
    ]);
}

/** The code in the last email that was queued. */
function resetCode(): string
{
    return Queue::pushed(SendEmailJob::class)->last()->email->mailable->code;
}

describe('asking for a reset code', function (): void {
    it('emails a code to a real staff member, on the critical queue, for the reset purpose', function (): void {
        staffForgot()->assertOk();

        Queue::assertPushedOn('critical', SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === 'akosua@example.com');
        $mail = Queue::pushed(SendEmailJob::class)->last()->email->mailable;
        expect($mail->purpose)->toBe(EmailCodePurpose::PasswordReset);
        $mail->assertHasSubject('Your password reset code for Grace Assembly');
        $log = MessageLog::query()->where('church_id', $this->church->id)->sole();
        expect($log->message_type)->toBe('password_reset_code')->and($log->content)->not->toContain(resetCode());
    });

    it('answers the same for an unknown email and for a deactivated account, and emails nobody', function (): void {
        $this->user->update(['is_active' => false]);

        $known = staffForgot(['email' => 'akosua@example.com']);
        $unknown = staffForgot(['email' => 'nobody@example.com']);

        expect($unknown->status())->toBe($known->status())->and($unknown->json())->toBe($known->json());
        $known->assertOk()->assertJsonPath('expires_in', 600);
        Queue::assertNothingPushed();
    });

    it('works for a staff member who has no password yet, which is how they set their first one', function (): void {
        $new = User::factory()->for($this->church)->create(['email' => 'new@example.com']);

        staffForgot(['email' => 'new@example.com'])->assertOk();

        Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === $new->email);
    });

    it('finds the staff member whatever the capitals or spaces', function (): void {
        staffForgot(['email' => '  AKOSUA@Example.com '])->assertOk();

        Queue::assertPushed(SendEmailJob::class);
    });

    it('limits codes to three a minute and ten an hour per email, and the answer is the same for an unknown email', function (): void {
        foreach (range(1, 3) as $ignored) {
            staffForgot()->assertOk();
        }
        staffForgot()->assertStatus(429)->assertHeader('Retry-After');
        foreach (range(1, 3) as $ignored) {
            staffForgot(['email' => 'nobody@example.com'])->assertOk();
        }
        staffForgot(['email' => 'nobody@example.com'])->assertStatus(429);
    });

    it('is a 404 on an address that is no church, and keeps each church apart', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        User::factory()->for($other)->create(['email' => 'stranger@example.com']);

        staffForgot([], 'nobody')->assertNotFound();
        staffForgot(['email' => 'stranger@example.com'])->assertOk();
        Queue::assertNothingPushed();
    });

    it('checks what was sent', function (): void {
        staffForgot(['email' => ''])->assertStatus(422)->assertJsonValidationErrors('email');
        staffForgot(['email' => 'not an email'])->assertStatus(422)->assertJsonValidationErrors('email');
    });
});

describe('choosing a new password', function (): void {
    it('sets the password, proves the email, and answers 204 with no body', function (): void {
        staffForgot()->assertOk();

        staffReset(resetCode())->assertNoContent();

        $this->user->refresh();
        expect(Hash::check('a brand new passphrase', $this->user->password))->toBeTrue()
            ->and(Hash::check('the old passphrase here', $this->user->password))->toBeFalse()
            ->and($this->user->email_verified_at)->not->toBeNull();
    });

    it('ends every token of the person, at once', function (): void {
        $this->user->createToken('one');
        $this->user->createToken('two');
        staffForgot()->assertOk();

        staffReset(resetCode())->assertNoContent();

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('ends a signed-in browser session, because the password it was opened with is gone', function (): void {
        $session = staffSignInFromBrowser($this);
        staffForgot()->assertOk();
        staffReset(resetCode())->assertNoContent();

        staffBrowserGet($session, '/api/v1/auth/me')->assertUnauthorized();
    });

    it('lets the person sign in with the new password and not the old one', function (): void {
        staffForgot()->assertOk();
        staffReset(resetCode())->assertNoContent();
        $this->travel(61)->seconds();

        $this->postJson('http://grace.localhost/api/v1/auth/login', ['email' => 'akosua@example.com', 'password' => 'the old passphrase here'])->assertStatus(422);
        $this->postJson('http://grace.localhost/api/v1/auth/login', ['email' => 'akosua@example.com', 'password' => 'a brand new passphrase'])->assertOk();
    });

    it('does not sign the person in by itself', function (): void {
        staffForgot()->assertOk();

        $response = staffReset(resetCode())->assertNoContent();

        expect($response->headers->getCookies())->toBe([])->and($this->user->tokens()->count())->toBe(0);
    });

    it('works once only', function (): void {
        staffForgot()->assertOk();
        $code = resetCode();
        staffReset($code)->assertNoContent();

        staffReset($code)->assertStatus(422)->assertExactJson(['message' => 'Invalid code']);
    });

    it('answers a wrong code, an expired code, an unknown email and a deactivated account the same way', function (): void {
        staffForgot()->assertOk();
        $code = resetCode();
        $wrong = staffReset($code === '000000' ? '111111' : '000000');
        $unknown = staffReset($code, ['email' => 'nobody@example.com']);
        $this->user->update(['is_active' => false]);
        $deactivated = staffReset($code);
        $this->travel(11)->minutes();
        $expired = staffReset($code);

        foreach ([$wrong, $unknown, $deactivated, $expired] as $response) {
            $response->assertStatus(422)->assertExactJson(['message' => 'Invalid code']);
        }
        expect(Hash::check('the old passphrase here', $this->user->refresh()->password))->toBeTrue();
    });

    it('locks after five wrong codes, even for the right one', function (): void {
        staffForgot()->assertOk();
        $code = resetCode();

        foreach (range(1, 4) as $ignored) {
            staffReset('000000')->assertStatus(422);
        }
        staffReset('000000')->assertStatus(429);

        staffReset($code)->assertStatus(429);
        expect(Hash::check('the old passphrase here', $this->user->refresh()->password))->toBeTrue();
    });

    it('does not accept a sign-in code to reset a password, or a reset code to sign in', function (): void {
        staffForgot()->assertOk();
        $resetCode = resetCode();
        $this->travel(61)->seconds();
        $this->postJson('http://grace.localhost/api/v1/auth/login', ['email' => 'akosua@example.com', 'password' => 'the old passphrase here'])->assertOk();
        $signInCode = Queue::pushed(SendEmailJob::class)->last()->email->mailable->code;

        staffReset($signInCode)->assertStatus(422);
        $this->postJson('http://grace.localhost/api/v1/auth/login/verify', ['email' => 'akosua@example.com', 'code' => $resetCode])->assertStatus(422);
    });

    it('does not accept a code made for another church', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        User::factory()->for($other)->create(['email' => 'akosua@example.com']);
        staffForgot()->assertOk();

        staffReset(resetCode(), slug: 'other')->assertStatus(422);
    });

    it('wants a password of at least 12 characters that matches its confirmation and is not the email', function (): void {
        staffForgot()->assertOk();
        $code = resetCode();

        staffReset($code, ['password' => 'short one', 'password_confirmation' => 'short one'])->assertStatus(422)->assertJsonValidationErrors('password');
        staffReset($code, ['password_confirmation' => 'a different passphrase'])->assertStatus(422)->assertJsonValidationErrors('password');
        staffReset($code, ['password' => 'akosua@example.com', 'password_confirmation' => 'akosua@example.com'])->assertStatus(422)->assertJsonValidationErrors('password');
        staffReset($code, ['password' => str_repeat('x', 73), 'password_confirmation' => str_repeat('x', 73)])->assertStatus(422)->assertJsonValidationErrors('password');
        staffReset($code, ['code' => '12345'])->assertStatus(422)->assertJsonValidationErrors('code');
        expect(Hash::check('the old passphrase here', $this->user->refresh()->password))->toBeTrue();
    });

    it('does not use up the code when the new password is refused, so the person can try again', function (): void {
        staffForgot()->assertOk();
        $code = resetCode();

        staffReset($code, ['password' => 'short one', 'password_confirmation' => 'short one'])->assertStatus(422);

        staffReset($code)->assertNoContent();
    });

    it('queues the breach check without ever putting the password on the queue', function (): void {
        staffForgot()->assertOk();

        staffReset(resetCode())->assertNoContent();

        Queue::assertPushedOn('notifications', CheckPasswordBreachJob::class);
        $job = Queue::pushed(CheckPasswordBreachJob::class)->sole();
        expect($job)->toBeInstanceOf(ShouldBeEncrypted::class)
            ->and(serialize($job))->not->toContain('a brand new passphrase')
            ->and($job->userId)->toBe($this->user->id);
    });

    it('limits code checks from one address to 10 a minute', function (): void {
        foreach (range(1, 10) as $number) {
            staffReset('000000', ['email' => "someone{$number}@example.com"])->assertStatus(422);
        }

        staffReset('000000', ['email' => 'someone11@example.com'])->assertStatus(429);
    });
});

/** Signs the person in by cookie, the way the SPA does, and returns the session id the browser keeps. */
function staffSignInFromBrowser($test): string
{
    $headers = ['Origin' => 'http://grace.localhost', 'Accept' => 'application/json'];
    $test->withHeaders($headers)->postJson('http://grace.localhost/api/v1/auth/login', ['email' => 'akosua@example.com', 'password' => 'the old passphrase here'])->assertOk();
    $code = Queue::pushed(SendEmailJob::class)->last()->email->mailable->code;
    $test->withHeaders($headers)->postJson('http://grace.localhost/api/v1/auth/login/verify', ['email' => 'akosua@example.com', 'code' => $code])->assertOk();
    $test->travel(61)->seconds();

    return app('session.store')->getId();
}

/** A later request from the same browser: every test request shares one app, so the guards and the session start clean. */
function staffBrowserGet(string $sessionId, string $path): TestResponse
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    return test()->withHeaders(['Origin' => 'http://grace.localhost', 'Accept' => 'application/json'])
        ->withCredentials()->withCookie(config('session.cookie'), $sessionId)->getJson("http://grace.localhost{$path}");
}
