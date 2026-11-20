<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Asks whether a password has appeared in a public data breach. It is a third-party call, so it only ever runs on a
 * worker (agreement section 3), and it is given the password's SHA-1 hash, never the password.
 */
interface PasswordBreachChecker
{
    /**
     * Whether a password with this SHA-1 hash (40 hex characters) is in the breach list. Throws when the list cannot be
     * reached, so the queue retries; it never answers "not breached" because of an error.
     */
    public function isBreached(string $sha1): bool;
}
