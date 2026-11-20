<?php

declare(strict_types=1);

use App\ValueObjects\Money;

it('keeps the amount as an exact two-place decimal string', function (string $input, string $expected): void {
    expect(Money::ghs($input)->amount)->toBe($expected);
})->with([
    ['2400', '2400.00'],
    ['2400.5', '2400.50'],
    ['0.1', '0.10'],
    ['99.999', '99.99'],
    ['-15.2', '-15.20'],
]);

it('formats with the currency and thousands separators', function (string $input, string $expected): void {
    expect(Money::ghs($input)->formatted())->toBe($expected);
})->with([
    ['0', 'GHS 0.00'],
    ['950.5', 'GHS 950.50'],
    ['2400', 'GHS 2,400.00'],
    ['1234567.89', 'GHS 1,234,567.89'],
    ['-1500', 'GHS -1,500.00'],
]);

it('does no float arithmetic, so large amounts stay exact', function (): void {
    expect(Money::ghs('9999999999.99')->formatted())->toBe('GHS 9,999,999,999.99');
});

it('refuses text that is not a number', function (): void {
    expect(fn () => Money::ghs('abc'))->toThrow(InvalidArgumentException::class);
});

it('is a string equal to its amount', function (): void {
    expect((string) Money::ghs('12'))->toBe('12.00');
});
