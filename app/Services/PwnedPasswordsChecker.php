<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PasswordBreachChecker;
use Illuminate\Support\Facades\Http;

/**
 * Checks a password against the Have I Been Pwned list using its k-anonymity range API: only the first five characters of
 * the SHA-1 hash are sent, and the answer is every hash ending that shares them, so the list never learns the password. The
 * request is padded, which adds fake entries with a count of zero; those are ignored. An error is thrown, not hidden.
 */
class PwnedPasswordsChecker implements PasswordBreachChecker
{
    /** How many characters of the hash are sent. */
    private const PREFIX_LENGTH = 5;

    /**
     * Whether a password with this hash is in the list.
     */
    public function isBreached(string $sha1): bool
    {
        $hash = strtoupper($sha1);
        $prefix = substr($hash, 0, self::PREFIX_LENGTH);
        $suffix = substr($hash, self::PREFIX_LENGTH);

        $body = Http::withHeaders(['Add-Padding' => 'true'])
            ->timeout((int) config('services.pwned_passwords.timeout'))
            ->get(config('services.pwned_passwords.url').'/'.$prefix)
            ->throw()
            ->body();

        return collect(explode("\n", $body))
            ->map(fn (string $line): array => explode(':', trim($line)) + [1 => '0'])
            ->contains(fn (array $entry): bool => strtoupper($entry[0]) === $suffix && (int) $entry[1] > 0);
    }
}
