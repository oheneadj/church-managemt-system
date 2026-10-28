<?php

declare(strict_types=1);

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Exceptions\SmsRejectedException;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use Illuminate\Contracts\Queue\Job;

function sampleSms(Church $church): OutboundSms
{
    return new OutboundSms($church->id, '+233244123456', 'Your code is 123456', 'otp', RecipientType::User, null, '[otp redacted]');
}

it('sends through the provider and logs the redacted message as sent', function (): void {
    $church = Church::factory()->create();
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->once()->withArgs(fn (OutboundSms $sms, string $key): bool => $sms->phone === '+233244123456' && $sms->message === 'Your code is 123456' && $key !== '')->andReturn(new SmsReceipt);

    (new SendSmsJob(sampleSms($church)))->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));

    $log = MessageLog::query()->first();
    expect($log->status)->toBe(MessageStatus::Sent)
        ->and($log->content)->toBe('[otp redacted]')
        ->and($log->church_id)->toBe($church->id);
});

it('logs a failure when the job gives up', function (): void {
    $church = Church::factory()->create();

    (new SendSmsJob(sampleSms($church)))->failed(new RuntimeException('provider down'));

    expect(MessageLog::query()->first()->status)->toBe(MessageStatus::Failed);
});

it('declares retry settings and a timeout', function (): void {
    $job = new SendSmsJob(sampleSms(Church::factory()->create()));

    expect($job->tries)->toBe(3)->and($job->timeout)->toBe(30)->and($job->backoff)->not->toBeEmpty();
});

it('logs a sign-up code that belongs to no church, and no church can see it', function (): void {
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->once()->andReturn(new SmsReceipt);
    $sms = new OutboundSms(null, '+233244300001', 'Your code is 123456', 'onboarding_otp', RecipientType::Applicant, null, '[otp redacted]');

    (new SendSmsJob($sms))->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));

    $church = Church::factory()->create();
    app()->instance('current.church', $church);

    expect(MessageLog::query()->withoutGlobalScopes()->whereNull('church_id')->count())->toBe(1)
        ->and(MessageLog::query()->count())->toBe(0);
});

it('keeps the provider reference and the safe parts of its reply in the log', function (): void {
    $church = Church::factory()->create();
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->once()->andReturn(new SmsReceipt('ref-123', ['status' => 'queued', 'credits' => 1]));

    (new SendSmsJob(sampleSms($church)))->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));

    $log = MessageLog::query()->first();
    expect($log->provider_reference)->toBe('ref-123')->and($log->provider_response)->toBe(['status' => 'queued', 'credits' => 1]);
});

it('sends every retry of one message with the same key, and a different key for another message', function (): void {
    $church = Church::factory()->create();
    $keys = [];
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->andReturnUsing(function (OutboundSms $sms, string $key) use (&$keys): SmsReceipt {
        $keys[] = $key;

        return new SmsReceipt;
    });

    $job = new SendSmsJob(sampleSms($church));
    $job->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));
    $retry = unserialize(serialize($job));
    $retry->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));
    (new SendSmsJob(sampleSms($church)))->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));

    expect($keys[0])->toBe($keys[1])->and($keys[2])->not->toBe($keys[0])->and($keys[0])->toMatch('/^[A-Za-z0-9._:-]{8,100}$/');
});

it('gives up at once, without retrying, when the provider refuses the message for good', function (): void {
    $church = Church::factory()->create();
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->once()->andThrow(new SmsRejectedException('refused'));
    $job = (new SendSmsJob(sampleSms($church)))->setJob($fakeJob = Mockery::mock(Job::class));
    $fakeJob->shouldReceive('fail')->once()->with(Mockery::type(SmsRejectedException::class));

    $job->handle($provider, app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));
});
