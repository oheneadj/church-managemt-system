<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueName;
use App\Jobs\CheckPasswordBreachJob;
use App\Models\User;

/**
 * Queues the check of a newly chosen password against the breach list (D-054). It is called with the plaintext only long
 * enough to take its SHA-1 hash; the plaintext is never put on a queue. The user must already carry the new password hash,
 * so the job can tell if the password changed again before it ran.
 */
class PasswordBreachScheduler
{
    /**
     * Queues the check on a worker, after the database change that set the password is committed.
     */
    public function schedule(User $user, string $password): void
    {
        CheckPasswordBreachJob::dispatch($user->id, $user->church_id, strtoupper(sha1($password)), (string) $user->password)
            ->onQueue(QueueName::Notifications->value)
            ->afterCommit();
    }
}
