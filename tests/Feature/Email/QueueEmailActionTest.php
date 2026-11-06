<?php

declare(strict_types=1);

use App\Actions\Email\QueueEmailAction;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

// Email is never sent inside a request (agreement section 3, rule 3): the action records the message and queues it.

beforeEach(function (): void {
    Queue::fake();
    Mail::fake();
    $this->church = Church::factory()->create();
});

/** An email carrying a code, for a church staff member. */
function outboundCode(?string $churchId, QueueName $queue = QueueName::Critical): OutboundEmail
{
    $mail = new EmailCodeMail('Grace Assembly', '482913', EmailCodePurpose::SignIn, 10);

    return new OutboundEmail(
        churchId: $churchId,
        email: 'akosua@example.com',
        mailable: $mail,
        messageType: 'sign_in_code',
        recipientType: RecipientType::User,
        logContent: $mail->redactedSummary(),
        recipientId: '01j00000000000000000000001',
        queue: $queue,
    );
}

it('writes a queued line to the message log and queues the send, without sending anything now', function (): void {
    $log = app(QueueEmailAction::class)->execute(outboundCode($this->church->id));

    expect($log->channel)->toBe(MessageChannel::Email)
        ->and($log->status)->toBe(MessageStatus::Queued)
        ->and($log->church_id)->toBe($this->church->id)
        ->and($log->recipient_email)->toBe('akosua@example.com')
        ->and($log->recipient_type)->toBe(RecipientType::User)
        ->and($log->message_type)->toBe('sign_in_code');
    Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $job): bool => $job->messageLogId === $log->id);
    Mail::assertNothingSent();
});

it('stores the redacted summary, never the code', function (): void {
    $log = app(QueueEmailAction::class)->execute(outboundCode($this->church->id));

    expect($log->content)->not->toContain('482913')->and($log->content)->toContain('sign-in code');
    expect(MessageLog::query()->withoutGlobalScopes()->where('content', 'like', '%482913%')->exists())->toBeFalse();
});

it('queues codes on the critical queue and anything else on the one it is given', function (): void {
    app(QueueEmailAction::class)->execute(outboundCode($this->church->id, QueueName::Critical));
    app(QueueEmailAction::class)->execute(outboundCode($this->church->id, QueueName::Notifications));

    Queue::assertPushedOn('critical', SendEmailJob::class);
    Queue::assertPushedOn('notifications', SendEmailJob::class);
});

it('can log an email that belongs to no church yet', function (): void {
    $log = app(QueueEmailAction::class)->execute(outboundCode(null));

    expect($log->church_id)->toBeNull();
});

it('keeps each church log to that church', function (): void {
    $other = Church::factory()->create();

    app(QueueEmailAction::class)->execute(outboundCode($this->church->id));
    app(QueueEmailAction::class)->execute(outboundCode($other->id));

    expect(MessageLog::query()->withoutGlobalScopes()->where('church_id', $this->church->id)->count())->toBe(1)
        ->and(MessageLog::query()->withoutGlobalScopes()->where('church_id', $other->id)->count())->toBe(1);
});

it('queues the send only after the log line is committed', function (): void {
    app(QueueEmailAction::class)->execute(outboundCode($this->church->id));

    Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $job): bool => $job->afterCommit === true);
});
