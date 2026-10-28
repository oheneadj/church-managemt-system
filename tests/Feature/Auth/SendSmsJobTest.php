<?php

declare(strict_types=1);

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;

function sampleSms(Church $church): OutboundSms
{
    return new OutboundSms($church->id, '+233244123456', 'Your code is 123456', 'otp', RecipientType::User, null, '[otp redacted]');
}

it('sends through the provider and logs the redacted message as sent', function (): void {
    $church = Church::factory()->create();
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->once()->with('+233244123456', 'Your code is 123456');

    (new SendSmsJob(sampleSms($church)))->handle($provider, app(MessageLogRepositoryInterface::class));

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
    $provider->shouldReceive('send')->once();
    $sms = new OutboundSms(null, '+233244300001', 'Your code is 123456', 'onboarding_otp', RecipientType::Applicant, null, '[otp redacted]');

    (new SendSmsJob($sms))->handle($provider, app(MessageLogRepositoryInterface::class));

    $church = Church::factory()->create();
    app()->instance('current.church', $church);

    expect(MessageLog::query()->withoutGlobalScopes()->whereNull('church_id')->count())->toBe(1)
        ->and(MessageLog::query()->count())->toBe(0);
});
