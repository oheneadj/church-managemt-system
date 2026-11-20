<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Email\QueueEmailAction;
use App\Contracts\PasswordBreachChecker;
use App\DTOs\OutboundEmail;
use App\Enums\QueueName;
use App\Enums\RecipientType;
use App\Mail\PasswordBreachedMail;
use App\Repositories\UserRepositoryInterface;
use App\Services\CircuitBreaker;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Checks a newly chosen staff password against a public breach list, on a worker, and emails the person to choose another
 * if it was found (D-054). The job is encrypted on the queue because it carries an unsalted SHA-1 of the password (what
 * the list needs); the plaintext is never on a queue. Nothing here blocks sign-in: if the list cannot be reached after the
 * retries, the failure is reported and the password stays, so a third party's outage can never lock anyone out.
 */
class CheckPasswordBreachJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** The name of this provider's circuit breaker. */
    private const CIRCUIT = 'pwned-passwords';

    /**
     * Takes the staff member, their church, the SHA-1 of the password and the hash of it that was stored, which tells
     * the job whether the person has chosen another password since.
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $churchId,
        public readonly string $sha1,
        public readonly string $passwordHash,
    ) {}

    /**
     * Checks the password and emails the person if it was found. It does nothing if the person is gone or has chosen a
     * newer password. While the circuit is open or the provider is paused, it waits and is put back on the queue.
     */
    public function handle(PasswordBreachChecker $checker, UserRepositoryInterface $users, QueueEmailAction $emails, CircuitBreaker $circuit): void
    {
        $user = $users->findById($this->userId);

        if ($user === null || $user->password !== $this->passwordHash) {
            return;
        }

        if (! $circuit->allows(self::CIRCUIT)) {
            $this->release(config('resilience.pwned_passwords.release_seconds'));

            return;
        }

        try {
            $breached = $checker->isBreached($this->sha1);
        } catch (Throwable $temporary) {
            $circuit->recordFailure(self::CIRCUIT);

            throw $temporary;
        }

        $circuit->recordSuccess(self::CIRCUIT);

        if (! $breached || $user->email === null) {
            return;
        }

        $mail = new PasswordBreachedMail((string) $user->church?->name);

        $emails->execute(new OutboundEmail(
            churchId: $this->churchId,
            email: $user->email,
            mailable: $mail,
            messageType: 'password_breached',
            recipientType: RecipientType::User,
            logContent: $mail->redactedSummary(),
            recipientId: $user->id,
            queue: QueueName::Notifications,
        ));
    }

    /**
     * Reports that the check could not be done after every retry, so someone can see the breach list is unreachable. The
     * password stays: this check must never lock a person out.
     */
    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
