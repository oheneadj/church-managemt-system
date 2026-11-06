<?php

declare(strict_types=1);

use App\Actions\Email\QueueEmailAction;
use App\Contracts\PasswordBreachChecker;
use App\Jobs\CheckPasswordBreachJob;
use App\Jobs\SendEmailJob;
use App\Mail\PasswordBreachedMail;
use App\Models\Church;
use App\Models\MessageLog;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Services\CircuitBreaker;
use App\Services\PasswordBreachScheduler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// The breach check runs on a worker, after a password is set, so no request ever waits for a third party (D-054). Only the
// first five characters of the password's SHA-1 hash leave the server, and the plaintext is never put on a queue.

beforeEach(function (): void {
    Cache::flush();
    Queue::fake();
    $this->church = Church::factory()->create(['name' => 'Grace Assembly']);
    $this->user = User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);
    $this->user->forceFill(['password' => 'a leaked passphrase here'])->save();
    $this->sha1 = strtoupper(sha1('a leaked passphrase here'));
});

/** The job for the user's current password, as the scheduler would make it. */
function breachJob(User $user, string $sha1): CheckPasswordBreachJob
{
    return new CheckPasswordBreachJob($user->id, $user->church_id, $sha1, $user->password);
}

/** Runs the job the way a worker does. */
function runBreachJob(CheckPasswordBreachJob $job): void
{
    $job->handle(app(PasswordBreachChecker::class), app(UserRepositoryInterface::class), app(QueueEmailAction::class), app(CircuitBreaker::class));
}

/** A reply from the breach list: the hash endings that were found, each with how many times. */
function breachReply(string ...$lines): PendingRequest|ResponseSequence|Closure|array
{
    return ['api.pwnedpasswords.com/range/*' => Http::response(implode("\r\n", $lines), 200)];
}

it('asks the breach list with only the first five characters of the hash, and with padding', function (): void {
    Http::fake(breachReply('0000000000000000000000000000000000A:3'));

    runBreachJob(breachJob($this->user, $this->sha1));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.pwnedpasswords.com/range/'.substr($this->sha1, 0, 5)
        && $request->hasHeader('Add-Padding', 'true'));
    Http::assertSentCount(1);
});

it('emails the person to choose another password when theirs is in the list', function (): void {
    Http::fake(breachReply(substr($this->sha1, 5).':41822', '0123456789ABCDEF0123456789ABCDEF012:2'));

    runBreachJob(breachJob($this->user, $this->sha1));

    Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === 'akosua@example.com' && $job->email->mailable instanceof PasswordBreachedMail);
    $log = MessageLog::query()->where('church_id', $this->church->id)->sole();
    expect($log->message_type)->toBe('password_breached')->and($log->recipient_email)->toBe('akosua@example.com');
});

it('says nothing about the password itself in that email, and points to Forgot password', function (): void {
    Http::fake(breachReply(substr($this->sha1, 5).':41822'));

    runBreachJob(breachJob($this->user, $this->sha1));

    $mail = Queue::pushed(SendEmailJob::class)->last()->email->mailable;
    $mail->assertSeeInText('Grace Assembly');
    $mail->assertSeeInText('Forgot password');
    $mail->assertDontSeeInText('a leaked passphrase here');
    $mail->assertHasSubject('Please choose a new password for Grace Assembly');
});

it('does nothing when the password is not in the list', function (): void {
    Http::fake(breachReply('0123456789ABCDEF0123456789ABCDEF012:2'));

    runBreachJob(breachJob($this->user, $this->sha1));

    Queue::assertNothingPushed();
});

it('ignores the padding lines the list adds, which have a count of zero', function (): void {
    Http::fake(breachReply(substr($this->sha1, 5).':0', '0123456789ABCDEF0123456789ABCDEF012:0'));

    runBreachJob(breachJob($this->user, $this->sha1));

    Queue::assertNothingPushed();
});

it('does nothing if the person has chosen another password since, and asks nobody', function (): void {
    Http::fake(breachReply(substr($this->sha1, 5).':41822'));
    $job = breachJob($this->user, $this->sha1);
    $this->user->forceFill(['password' => 'a much newer passphrase'])->save();

    runBreachJob($job);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('does nothing for a staff member who is gone', function (): void {
    Http::fake(breachReply(substr($this->sha1, 5).':41822'));
    $job = breachJob($this->user, $this->sha1);
    $this->user->delete();

    runBreachJob($job);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('throws when the list cannot be reached, so the queue retries, and never lets the password in unnoticed or blocks the person', function (): void {
    Http::fake(['api.pwnedpasswords.com/range/*' => Http::response('', 503)]);

    expect(fn () => runBreachJob(breachJob($this->user, $this->sha1)))->toThrow(Exception::class);
    Queue::assertNothingPushed();
});

it('counts a failed call against the circuit and holds back while it is open or paused', function (): void {
    Http::fake(breachReply(substr($this->sha1, 5).':41822'));
    $circuit = app(CircuitBreaker::class);
    $circuit->pause('pwned-passwords');
    $job = breachJob($this->user, $this->sha1);
    $queued = Mockery::mock(Job::class);
    $queued->shouldReceive('release')->once();
    $job->setJob($queued);

    runBreachJob($job);

    Http::assertNothingSent();
});

it('is encrypted on the queue and carries no plaintext password', function (): void {
    $job = breachJob($this->user, $this->sha1);

    expect($job)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and(serialize($job))->not->toContain('a leaked passphrase here');
});

it('declares its retries, backoff, timeout and a failed handler that reports', function (): void {
    $job = breachJob($this->user, $this->sha1);
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): void {
        $reported[] = $e->getMessage();
    });

    $job->failed(new RuntimeException('breach list unreachable'));

    expect($job->tries)->toBe(3)->and($job->timeout)->toBe(30)->and($job->backoff)->toBe([60, 300, 900])
        ->and($reported)->toContain('breach list unreachable');
});

it('is scheduled by hashing the password and keeping the plaintext off the queue, on the notifications queue after commit', function (): void {
    app(PasswordBreachScheduler::class)->schedule($this->user, 'a leaked passphrase here');

    Queue::assertPushedOn('notifications', CheckPasswordBreachJob::class, fn (CheckPasswordBreachJob $job): bool => $job->sha1 === $this->sha1 && $job->userId === $this->user->id && $job->afterCommit === true);
    expect(serialize(Queue::pushed(CheckPasswordBreachJob::class)->sole()))->not->toContain('a leaked passphrase here');
});
