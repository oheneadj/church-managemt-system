<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A keyed hash of a phone number (D-073). The public giving page keeps the phone someone paid from only in this form, so a repeated
 * attempt from the same phone can be recognised without the number being stored. The same number always gives the same hash, and
 * without the application key nobody can work a number out from it.
 */
final class PhoneFingerprint
{
    /**
     * The hash of a phone number in international form.
     */
    public function of(string $e164): string
    {
        return hash_hmac('sha256', $e164, (string) config('app.key'));
    }
}
