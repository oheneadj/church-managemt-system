<?php

declare(strict_types=1);

use App\Jobs\SendEmailJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

// D-054: staff sign in with email and password, then a 6-digit code emailed to them. Two steps, one door each.

const STAFF_PASSWORD = 'a long passphrase here';

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->user = User::factory()->for($this->church)->create(['email' => 'akosua@example.com', 'name' => 'Akosua Mensah']);
    $this->user->forceFill(['password' => STAFF_PASSWORD])->save();
    $this->user->assignRole('church-admin');
});

/** Step 1: email and password, as the sign-in screen sends them. */
function staffPassword(array $body = [], string $slug = 'grace'): TestResponse
{
    return test()->postJson("http://{$slug}.localhost/api/v1/auth/login", [...['email' => 'akosua@example.com', 'password' => STAFF_PASSWORD], ...$body]);
}

/** Step 2: the emailed code. A request from the church's own page is signed in by cookie. */
function staffCode(string $code, array $body = [], string $slug = 'grace', bool $browser = false): TestResponse
{
    $headers = $browser ? ['Origin' => "http://{$slug}.localhost", 'Accept' => 'application/json'] : [];

    return test()->withHeaders($headers)->postJson("http://{$slug}.localhost/api/v1/auth/login/verify", [...['email' => 'akosua@example.com', 'code' => $code], ...$body]);
}

/** The code that was put in the queued email, so a test can type it in as the person would. */
function emailedCode(): string
{
    return Queue::pushed(SendEmailJob::class)->last()->email->mailable->code;
}

describe('step 1: the password', function (): void {
    it('emails a code when the password is right, and says nothing else', function (): void {
        staffPassword()->assertOk()->assertExactJson(['message' => 'Code sent', 'expires_in' => 600]);

        Queue::assertPushedOn('critical', SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === 'akosua@example.com' && $job->email->churchId === $this->church->id);
        expect(emailedCode())->toMatch('/^\d{6}$/');
    });

    it('records the email in the message log without the code', function (): void {
        staffPassword()->assertOk();

        $log = MessageLog::query()->where('church_id', $this->church->id)->sole();
        expect($log->recipient_email)->toBe('akosua@example.com')
            ->and($log->message_type)->toBe('sign_in_code')
            ->and($log->content)->not->toContain(emailedCode());
    });

    it('uses the church name and a sign-in purpose in the email', function (): void {
        staffPassword()->assertOk();

        $mail = Queue::pushed(SendEmailJob::class)->last()->email->mailable;
        $mail->assertSeeInText('Grace Assembly');
        $mail->assertHasSubject('Your sign-in code for Grace Assembly');
    });

    it('finds the staff member whatever the capitals or spaces in the email', function (): void {
        staffPassword(['email' => '  Akosua@EXAMPLE.com '])->assertOk();
    });

    it('gives one answer for a wrong password, an unknown email and an account with no password', function (): void {
        $noPassword = User::factory()->for($this->church)->create(['email' => 'new@example.com']);

        $wrong = staffPassword(['password' => 'not the password']);
        $unknown = staffPassword(['email' => 'nobody@example.com']);
        $none = staffPassword(['email' => $noPassword->email]);

        foreach ([$wrong, $unknown, $none] as $response) {
            $response->assertStatus(422)->assertExactJson(['message' => 'Invalid email or password']);
        }
        Queue::assertNothingPushed();
    });

    it('tells a deactivated person so, but only once their password is right, and sends no code', function (): void {
        $this->user->update(['is_active' => false]);

        staffPassword()->assertForbidden()->assertExactJson(['message' => 'Your account has been deactivated. Contact your church admin.']);
        staffPassword(['password' => 'not the password'])->assertStatus(422)->assertExactJson(['message' => 'Invalid email or password']);
        Queue::assertNothingPushed();
    });

    it('locks the email for 15 minutes after five wrong passwords, even for the right one', function (): void {
        foreach (range(1, 4) as $ignored) {
            staffPassword(['password' => 'wrong'])->assertStatus(422);
            $this->travel(5)->seconds();
        }

        staffPassword(['password' => 'wrong'])->assertStatus(429)->assertHeader('Retry-After', '900');
        staffPassword()->assertStatus(429);
        Queue::assertNothingPushed();
    });

    it('does not lock another email when one is locked', function (): void {
        $other = User::factory()->for($this->church)->create(['email' => 'other@example.com']);
        $other->forceFill(['password' => STAFF_PASSWORD])->save();
        foreach (range(1, 4) as $ignored) {
            staffPassword(['password' => 'wrong'])->assertStatus(422);
            $this->travel(5)->seconds();
        }
        staffPassword(['password' => 'wrong'])->assertStatus(429);

        staffPassword(['email' => 'other@example.com'])->assertOk();
    });

    it('forgets earlier wrong passwords once the right one is entered', function (): void {
        foreach (range(1, 4) as $ignored) {
            staffPassword(['password' => 'wrong'])->assertStatus(422);
            $this->travel(5)->seconds();
        }
        staffPassword()->assertOk();
        $this->travel(61)->seconds();

        foreach (range(1, 4) as $ignored) {
            staffPassword(['password' => 'wrong'])->assertStatus(422);
            $this->travel(5)->seconds();
        }
        staffPassword()->assertOk();
    });

    it('does not use up the code allowance on a wrong password', function (): void {
        staffPassword(['password' => 'wrong'])->assertStatus(422);

        staffPassword()->assertOk();
    });

    it('allows three codes a minute and ten an hour for one email', function (): void {
        foreach (range(1, 3) as $ignored) {
            staffPassword()->assertOk();
        }
        staffPassword()->assertStatus(429)->assertHeader('Retry-After');

        foreach (range(1, 2) as $ignored) {
            $this->travel(61)->seconds();
            foreach (range(1, 3) as $again) {
                staffPassword()->assertOk();
            }
        }
        $this->travel(61)->seconds();
        staffPassword()->assertOk();
        $this->travel(61)->seconds();

        staffPassword()->assertStatus(429);
    });

    it('allows 20 codes an hour from one address', function (): void {
        foreach (range(1, 20) as $number) {
            $staff = User::factory()->for($this->church)->create(['email' => "staff{$number}@example.com"]);
            $staff->forceFill(['password' => STAFF_PASSWORD])->save();
            staffPassword(['email' => $staff->email])->assertOk();
            $this->travel(4)->seconds();
        }
        $extra = User::factory()->for($this->church)->create(['email' => 'staff21@example.com']);
        $extra->forceFill(['password' => STAFF_PASSWORD])->save();

        staffPassword(['email' => $extra->email])->assertStatus(429);
    });

    it('limits password guesses from one address to 20 a minute', function (): void {
        foreach (range(1, 20) as $number) {
            staffPassword(['email' => "guess{$number}@example.com", 'password' => 'x'])->assertStatus(422);
        }

        staffPassword(['email' => 'guess21@example.com', 'password' => 'x'])->assertStatus(429);
    });

    it('keeps each church apart: staff of another church cannot sign in here, and the limits are not shared', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        $stranger = User::factory()->for($other)->create(['email' => 'stranger@example.com']);
        $stranger->forceFill(['password' => STAFF_PASSWORD])->save();

        staffPassword(['email' => 'stranger@example.com'])->assertStatus(422)->assertExactJson(['message' => 'Invalid email or password']);
        staffPassword(['email' => 'stranger@example.com'], 'other')->assertOk();
        staffPassword()->assertOk();
    });

    it('is a 404 on an address that is no church', function (): void {
        staffPassword([], 'nobody')->assertNotFound();
    });

    it('checks what was sent', function (): void {
        staffPassword(['email' => ''])->assertStatus(422)->assertJsonValidationErrors('email');
        staffPassword(['email' => 'not an email'])->assertStatus(422)->assertJsonValidationErrors('email');
        staffPassword(['password' => ''])->assertStatus(422)->assertJsonValidationErrors('password');
    });

    it('never puts the password or the code in the response', function (): void {
        $body = staffPassword()->assertOk()->getContent();

        expect($body)->not->toContain(STAFF_PASSWORD)->and($body)->not->toContain(emailedCode());
    });
});

describe('step 2: the emailed code', function (): void {
    it('signs a browser in by cookie, with no token, and the person is who they said', function (): void {
        staffPassword()->assertOk();

        $response = staffCode(emailedCode(), browser: true)->assertOk();

        $response->assertJsonMissingPath('token')->assertJsonPath('user.id', $this->user->id)->assertJsonPath('user.email', 'akosua@example.com')->assertJsonPath('church.slug', 'grace');
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));
        expect($cookie->isHttpOnly())->toBeTrue()->and($cookie->getDomain())->toBeNull()
            ->and($this->user->tokens()->count())->toBe(0);
    });

    it('gives another kind of client a token that lasts 8 hours', function (): void {
        staffPassword()->assertOk();

        $response = staffCode(emailedCode())->assertOk()->assertJsonPath('token_type', 'Bearer');

        expect($response->json('token'))->not->toBeEmpty();
        $token = $this->user->tokens()->sole();
        expect($token->expires_at->diffInHours(now(), true))->toBeGreaterThan(7)->and($token->expires_at->diffInHours(now(), true))->toBeLessThanOrEqual(8);
    });

    it('works once only', function (): void {
        staffPassword()->assertOk();
        $code = emailedCode();

        staffCode($code)->assertOk();

        staffCode($code)->assertStatus(422)->assertExactJson(['message' => 'Invalid code']);
    });

    it('says only "Invalid code" for a wrong code, an expired code and an unknown email', function (): void {
        staffPassword()->assertOk();
        $code = emailedCode();
        $wrong = staffCode($code === '000000' ? '111111' : '000000');
        $unknown = staffCode($code, ['email' => 'nobody@example.com']);
        $this->travel(11)->minutes();
        $expired = staffCode($code);

        foreach ([$wrong, $unknown, $expired] as $response) {
            $response->assertStatus(422)->assertExactJson(['message' => 'Invalid code']);
        }
    });

    it('locks after five wrong codes, even for the right one', function (): void {
        staffPassword()->assertOk();
        $code = emailedCode();

        foreach (range(1, 4) as $ignored) {
            staffCode('000000')->assertStatus(422);
        }
        staffCode('000000')->assertStatus(429)->assertHeader('Retry-After', '900');

        staffCode($code)->assertStatus(429);
        expect($this->user->tokens()->count())->toBe(0);
    });

    it('does not accept a code made for another email, or for another church', function (): void {
        $other = User::factory()->for($this->church)->create(['email' => 'other@example.com']);
        $elsewhere = Church::factory()->create(['slug' => 'other']);
        $same = User::factory()->for($elsewhere)->create(['email' => 'akosua@example.com']);
        $same->forceFill(['password' => STAFF_PASSWORD])->save();
        staffPassword()->assertOk();
        $code = emailedCode();

        staffCode($code, ['email' => $other->email])->assertStatus(422);
        staffCode($code, slug: 'other')->assertStatus(422);
    });

    it('stamps the email as verified on the first code that works, and leaves it alone after', function (): void {
        staffPassword()->assertOk();
        staffCode(emailedCode())->assertOk();
        $first = $this->user->refresh()->email_verified_at;
        $this->travel(61)->seconds();

        staffPassword()->assertOk();
        staffCode(emailedCode())->assertOk();

        expect($first)->not->toBeNull()->and($this->user->refresh()->email_verified_at->equalTo($first))->toBeTrue();
    });

    it('stamps the time of the sign-in', function (): void {
        staffPassword()->assertOk();
        staffCode(emailedCode())->assertOk();

        expect($this->user->refresh()->last_login_at)->not->toBeNull();
    });

    it('refuses a person who was deactivated between the password and the code', function (): void {
        staffPassword()->assertOk();
        $code = emailedCode();
        $this->user->update(['is_active' => false]);

        staffCode($code)->assertStatus(422)->assertExactJson(['message' => 'Invalid code']);
    });

    it('limits verification attempts from one address to 10 a minute', function (): void {
        foreach (range(1, 10) as $number) {
            staffCode('000000', ['email' => "someone{$number}@example.com"])->assertStatus(422);
        }

        staffCode('000000', ['email' => 'someone11@example.com'])->assertStatus(429);
    });

    it('checks what was sent', function (): void {
        staffCode('12345')->assertStatus(422)->assertJsonValidationErrors('code');
        staffCode('abcdef')->assertStatus(422)->assertJsonValidationErrors('code');
        staffCode('123456', ['email' => ''])->assertStatus(422)->assertJsonValidationErrors('email');
    });

    it('is not a way round the password: a code cannot be asked for without it, and the old phone codes do not work here', function (): void {
        Cache::put("church:{$this->church->id}:otp:".hash('sha256', $this->user->phone), Hash::make('123456'), 600);

        staffCode('123456')->assertStatus(422);
    });
});
