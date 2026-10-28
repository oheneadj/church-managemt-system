<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->user = User::factory()->for($this->church)->create(['phone' => '+233244123456']);
    $this->user->assignRole('finance-secretary');
});

/**
 * Plants a known code the same way the service stores it.
 */
function plantCode(Church $church, string $phone, string $code): void
{
    Cache::put("church:{$church->id}:otp:".hash('sha256', $phone), Hash::make($code), 600);
}

it('exchanges a valid code for a token, the user and the church', function (): void {
    plantCode($this->church, '+233244123456', '123456');

    $response = $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])
        ->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'phone', 'roles', 'permissions'], 'church' => ['id', 'name', 'slug', 'plan', 'sms_credits']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $this->user->id)
        ->assertJsonPath('user.roles', ['finance-secretary'])
        ->assertJsonPath('church.slug', 'grace');

    expect($response->json('user.permissions'))->toContain('giving.record');
    expect($this->user->refresh()->last_login_at)->not->toBeNull();
});

it('issues a token that expires in 8 hours and opens the protected routes', function (): void {
    plantCode($this->church, '+233244123456', '123456');

    $token = $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->json('token');

    $record = $this->user->tokens()->first();
    expect((int) round(now()->diffInHours($record->expires_at, absolute: true)))->toBe(8);

    $this->withToken($token)->getJson('http://grace.localhost/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $this->user->id);
});

it('accepts a code only once', function (): void {
    plantCode($this->church, '+233244123456', '123456');

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->assertOk();
    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->assertUnprocessable();
});

it('gives the same answer for a wrong code and for no code at all', function (): void {
    plantCode($this->church, '+233244123456', '123456');

    $wrong = $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '000000']);
    $none = $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233200999999', 'otp' => '000000']);

    $wrong->assertUnprocessable()->assertJson(['message' => 'Invalid code']);
    $none->assertUnprocessable();
    expect($wrong->json())->toBe($none->json());
});

it('locks the phone out on the fifth wrong code, even for the right code afterwards', function (): void {
    plantCode($this->church, '+233244123456', '123456');

    foreach (range(1, 4) as $attempt) {
        $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '000000'])->assertUnprocessable();
    }

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '000000'])
        ->assertTooManyRequests()
        ->assertJson(['message' => 'Too many attempts. Try again in 15 minutes.'])
        ->assertHeader('Retry-After', '900');

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->assertTooManyRequests();
});

it('lets the phone sign in again once the lockout is reset', function (): void {
    plantCode($this->church, '+233244123456', '123456');
    foreach (range(1, 5) as $attempt) {
        $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '000000']);
    }

    $this->artisan('otp:reset-lockout', ['church' => 'grace', 'phone' => '0244123456'])->assertSuccessful();
    plantCode($this->church, '+233244123456', '123456');

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->assertOk();
});

it('fails the reset command for an unknown church', function (): void {
    $this->artisan('otp:reset-lockout', ['church' => 'nope', 'phone' => '0244123456'])->assertFailed();
});

it('does not accept a code planted for another church', function (): void {
    $other = Church::factory()->create(['slug' => 'other']);
    User::factory()->for($other)->create(['phone' => '+233244123456']);
    plantCode($this->church, '+233244123456', '123456');

    $this->postJson('http://other.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->assertUnprocessable();
});

it('refuses a code for a staff member deactivated after it was sent', function (): void {
    plantCode($this->church, '+233244123456', '123456');
    $this->user->update(['is_active' => false]);

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])->assertUnprocessable();
});

it('validates the code format', function (): void {
    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('otp');
});

it('completes a full request then verify round trip', function (): void {
    Queue::fake();
    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '0244123456'])->assertOk();

    $code = null;
    Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use (&$code): bool {
        preg_match('/\b(\d{6})\b/', $job->sms->message, $matches);
        $code = $matches[1];

        return true;
    });

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '0244123456', 'otp' => $code])->assertOk();
});
