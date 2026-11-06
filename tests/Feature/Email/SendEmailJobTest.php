<?php

declare(strict_types=1);

use App\DTOs\OutboundEmail;
use App\Enums\EmailCodePurpose;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\QueueName;
use App\Enums\RecipientType;
use App\Jobs\SendEmailJob;
use App\Mail\EmailCodeMail;
use App\Models\Church;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// The worker side of email: send once, record the outcome, hold back around a failing provider, fail loudly and for good.

beforeEach(function (): void {
    Cache::flush();
    $this->church = Church::factory()->create();
    $this->circuit = app(CircuitBreaker::class);
});

/** A queued log line and the job that will send it. */
function emailJob(Church $church): array
{
    $mail = new EmailCodeMail('Grace Assembly', '482913', EmailCodePurpose::SignIn, 10);
    $log = MessageLog::query()->create([
        'church_id' => $church->id,
        'channel' => MessageChannel::Email,
        'recipient_type' => RecipientType::User,
        'recipient_email' => 'akosua@example.com',
        'message_type' => 'sign_in_code',
        'content' => $mail->redactedSummary(),
        'status' => MessageStatus::Queued,
    ]);
    $email = new OutboundEmail($church->id, 'akosua@example.com', $mail, 'sign_in_code', RecipientType::User, $mail->redactedSummary(), queue: QueueName::Critical);

    return [new SendEmailJob($email, $log->id), $log];
}

/** Runs the job the way a worker does. */
function runJob(SendEmailJob $job): void
{
    $job->handle(app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));
}

it('sends the email to the address and records it as sent with the mail provider reference', function (): void {
    [$job, $log] = emailJob($this->church);

    runJob($job);

    $sent = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1)
        ->and($sent->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe('akosua@example.com');
    $log->refresh();
    expect($log->status)->toBe(MessageStatus::Sent)
        ->and($log->provider_reference)->not->toBeNull()
        ->and($log->sent_at)->not->toBeNull();
});

it('sends the same email only once, however often the job runs', function (): void {
    Mail::fake();
    [$job] = emailJob($this->church);

    runJob($job);
    runJob($job);

    Mail::assertSentCount(1);
});

it('does nothing for a log line that is gone', function (): void {
    Mail::fake();
    [$job, $log] = emailJob($this->church);
    MessageLog::query()->withoutGlobalScopes()->whereKey($log->id)->delete();

    runJob($job);

    Mail::assertNothingSent();
});

it('holds the email back while the circuit is open, and sends nothing', function (): void {
    Mail::fake();
    foreach (range(1, config('resilience.circuit_breaker.failure_threshold')) as $ignored) {
        $this->circuit->recordFailure('email');
    }
    [$job, $log] = emailJob($this->church);
    $queued = Mockery::mock(Job::class);
    $queued->shouldReceive('release')->once()->with(config('resilience.email.release_seconds'));
    $job->setJob($queued);

    runJob($job);

    Mail::assertNothingSent();
    expect($log->refresh()->status)->toBe(MessageStatus::Queued);
});

it('holds the email back while a person has paused email', function (): void {
    Mail::fake();
    $this->circuit->pause('email');
    [$job] = emailJob($this->church);
    $queued = Mockery::mock(Job::class);
    $queued->shouldReceive('release')->once();
    $job->setJob($queued);

    runJob($job);

    Mail::assertNothingSent();
});

it('counts a failed send against the email circuit and lets the job retry', function (): void {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('connection refused'));
    [$job] = emailJob($this->church);

    expect(fn () => runJob($job))->toThrow(RuntimeException::class, 'connection refused');

    $this->circuit->recordSuccess('email');
});

it('opens the circuit after the threshold of failed sends', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('connection refused'));
    foreach (range(1, config('resilience.circuit_breaker.failure_threshold')) as $ignored) {
        [$job] = emailJob($this->church);
        try {
            runJob($job);
        } catch (RuntimeException) {
        }
    }

    expect($this->circuit->allows('email'))->toBeFalse();
});

it('closes the circuit again after a send that works', function (): void {
    foreach (range(1, config('resilience.circuit_breaker.failure_threshold') - 1) as $ignored) {
        $this->circuit->recordFailure('email');
    }
    [$job] = emailJob($this->church);

    runJob($job);

    $this->circuit->recordFailure('email');
    expect($this->circuit->allows('email'))->toBeTrue();
});

it('records the email as failed and reports it once every retry is used up', function (): void {
    [$job, $log] = emailJob($this->church);
    $problem = new RuntimeException('mailbox unavailable');
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): void {
        $reported[] = $e->getMessage();
    });

    $job->failed($problem);

    expect($log->refresh()->status)->toBe(MessageStatus::Failed)
        ->and($reported)->toContain('mailbox unavailable');
});

it('declares its retries, backoff, timeout and a retry window, like every queued job', function (): void {
    [$job] = emailJob($this->church);

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(30)
        ->and($job->backoff)->toBe([30, 120, 300])
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
});

it('never puts the address or the code in the application log', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    [$job] = emailJob($this->church);
    Log::spy();

    try {
        runJob($job);
    } catch (RuntimeException) {
    }

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning', fn (string $message, array $context = []): bool => str_contains($message.json_encode($context), 'akosua@example.com') || str_contains($message.json_encode($context), '482913'));
});
