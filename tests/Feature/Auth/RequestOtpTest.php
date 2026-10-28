<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->user = User::factory()->for($this->church)->create(['phone' => '+233244123456']);
});

it('queues a code on the critical queue for a known staff phone', function (): void {
    Queue::fake();

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])
        ->assertOk()
        ->assertJson(['message' => 'OTP sent', 'expires_in' => 600]);

    Queue::assertPushedOn('critical', SendSmsJob::class, function (SendSmsJob $job): bool {
        return $job->sms->churchId === $this->church->id
            && $job->sms->phone === '+233244123456'
            && $job->sms->logContent === '[otp redacted]';
    });
});

it('sends the message in the documented format', function (): void {
    Queue::fake();

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertOk();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => preg_match('/^Your .+ code is: \d{6}\. Valid for 10 minutes\. Do not share\.$/', $job->sms->message) === 1);
});

it('accepts a local Ghana number and normalises it', function (): void {
    Queue::fake();

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '024 412 3456'])->assertOk();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->phone === '+233244123456');
});

it('stores only a hash of the code, never the code itself', function (): void {
    Queue::fake();

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertOk();

    $code = null;
    Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use (&$code): bool {
        preg_match('/\b(\d{6})\b/', $job->sms->message, $matches);
        $code = $matches[1];

        return true;
    });

    $stored = Cache::get("church:{$this->church->id}:otp:".hash('sha256', '+233244123456'));

    expect($stored)->toBeString()->not->toBe($code);
});

it('answers the same way for an unknown phone and sends nothing', function (): void {
    Queue::fake();

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233200999999'])
        ->assertOk()
        ->assertJson(['message' => 'OTP sent']);

    Queue::assertNothingPushed();
});

it('does not send a code for an inactive staff member', function (): void {
    Queue::fake();
    $this->user->update(['is_active' => false]);

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertOk();

    Queue::assertNothingPushed();
});

it('does not send a code when the phone belongs to another church', function (): void {
    Queue::fake();
    Church::factory()->create(['slug' => 'other']);

    $this->postJson('http://other.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertOk();

    Queue::assertNothingPushed();
});

it('rejects a malformed phone number', function (): void {
    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => 'not-a-phone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('requires a phone number', function (): void {
    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', [])->assertJsonValidationErrors('phone');
});

it('returns 404 for an unknown church', function (): void {
    $this->postJson('http://nobody.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertNotFound();
});

it('allows only one code request a minute per phone', function (): void {
    Queue::fake();

    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertOk();
    $this->postJson('http://grace.localhost/api/v1/auth/otp/request', ['phone' => '+233244123456'])->assertTooManyRequests();
});
