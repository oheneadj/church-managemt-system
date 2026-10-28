<?php

declare(strict_types=1);

use App\ValueObjects\PhoneNumber;

it('normalises accepted Ghana formats to E.164', function (string $input): void {
    expect(PhoneNumber::tryFrom($input)?->e164)->toBe('+233244123456');
})->with(['0244123456', '024 412 3456', '233244123456', '+233244123456', '+233 (244) 123-456']);

it('rejects input that is not a phone number', function (string $input): void {
    expect(PhoneNumber::tryFrom($input))->toBeNull();
})->with(['', 'abc', '12345', '+0244123456', '+233']);
