<?php

declare(strict_types=1);

// The React app repeats a few server limits so it can check input before the round trip
// (resources/js/constants/onboarding.ts and password.ts). This fails if a number there drifts from its config value.

/** Reads `export const NAME = 12;` out of the TypeScript file. */
function frontendNumber(string $constant, string $file = 'onboarding'): int
{
    $source = (string) file_get_contents(base_path("resources/js/constants/{$file}.ts"));
    preg_match('/export const '.$constant.' = (\d+);/', $source, $match);

    return (int) ($match[1] ?? -1);
}

it('mirrors the sign-up limits', function (string $constant, string $configKey): void {
    expect(frontendNumber($constant))->toBe(config($configKey));
})->with([
    'slug min' => ['SLUG_MIN_LENGTH', 'onboarding.slug_min_length'],
    'slug max' => ['SLUG_MAX_LENGTH', 'onboarding.slug_max_length'],
    'prefix min' => ['PREFIX_MIN_LENGTH', 'onboarding.prefix_min_length'],
    'prefix max' => ['PREFIX_MAX_LENGTH', 'onboarding.prefix_max_length'],
    'code length' => ['OTP_LENGTH', 'otp.length'],
]);

it('mirrors the staff password limits', function (string $constant, string $configKey): void {
    expect(frontendNumber($constant, 'password'))->toBe(config($configKey));
})->with([
    'password min' => ['PASSWORD_MIN_LENGTH', 'otp.password_min_length'],
    'password max bytes' => ['PASSWORD_MAX_BYTES', 'otp.password_max_bytes'],
]);
