<?php

declare(strict_types=1);

use App\Services\GivingFeeCalculator;
use App\ValueObjects\Money;

// The fees on a digital gift (D-018, D-072, docs/04 Platform Fee, QA-15): exact decimals, half-up, the floor and the cap.

beforeEach(function (): void {
    config([
        'giving.platform_fee_rate' => '0.01', 'giving.platform_fee_cap' => '10.00', 'giving.platform_fee_floor' => '0.10',
        'giving.collection_fee_rate' => '0.0199', 'giving.min_digital_gift' => '1.00', 'giving.max_digital_gift' => '30000.00',
    ]);
    $this->fees = new GivingFeeCalculator;
});

it('takes the rate of the gift as the platform fee', function (string $gift, string $fee): void {
    expect($this->fees->platformFee(Money::ghs($gift))->amount)->toBe($fee);
})->with([
    'GHS 100' => ['100.00', '1.00'],
    'GHS 250' => ['250.00', '2.50'],
    'GHS 999.99' => ['999.99', '10.00'],
]);

it('never takes less than the floor', function (): void {
    expect($this->fees->platformFee(Money::ghs('1.00'))->amount)->toBe('0.10')
        ->and($this->fees->platformFee(Money::ghs('5.00'))->amount)->toBe('0.10')
        ->and($this->fees->platformFee(Money::ghs('10.00'))->amount)->toBe('0.10');
});

it('never takes more than the cap', function (): void {
    expect($this->fees->platformFee(Money::ghs('1000.00'))->amount)->toBe('10.00')
        ->and($this->fees->platformFee(Money::ghs('30000.00'))->amount)->toBe('10.00');
});

it('rounds half-up to two places, so the half pesewa goes up', function (): void {
    // 10.50 x 1% = 0.105, 20.50 x 1% = 0.205 (both ties), 33.33 x 1% = 0.3333 (down).
    expect($this->fees->platformFee(Money::ghs('10.50'))->amount)->toBe('0.11')
        ->and($this->fees->platformFee(Money::ghs('20.50'))->amount)->toBe('0.21')
        ->and($this->fees->platformFee(Money::ghs('33.33'))->amount)->toBe('0.33');
});

it('takes the collection fee as the rate of the gift, rounded half-up', function (string $gift, string $fee): void {
    expect($this->fees->collectionFee(Money::ghs($gift))->amount)->toBe($fee);
})->with([
    'GHS 100' => ['100.00', '1.99'],
    'GHS 1' => ['1.00', '0.02'],
    'GHS 50' => ['50.00', '1.00'],
    'GHS 30000' => ['30000.00', '597.00'],
]);

it('asks the giver for the gift and the collection fee together', function (): void {
    expect($this->fees->totalToPay(Money::ghs('100.00'))->amount)->toBe('101.99')
        ->and($this->fees->totalToPay(Money::ghs('50.00'))->amount)->toBe('51.00');
});

it('reads the rates from the configuration, not from the code', function (): void {
    config(['giving.platform_fee_rate' => '0.02', 'giving.platform_fee_cap' => '20.00', 'giving.platform_fee_floor' => '0.50', 'giving.collection_fee_rate' => '0.01']);

    expect($this->fees->platformFee(Money::ghs('100.00'))->amount)->toBe('2.00')
        ->and($this->fees->platformFee(Money::ghs('10.00'))->amount)->toBe('0.50')
        ->and($this->fees->platformFee(Money::ghs('5000.00'))->amount)->toBe('20.00')
        ->and($this->fees->collectionFee(Money::ghs('100.00'))->amount)->toBe('1.00');
});

it('accepts a gift from the minimum to the maximum, both included, and refuses the rest', function (string $gift, bool $ok): void {
    expect($this->fees->isWithinLimits(Money::ghs($gift)))->toBe($ok);
})->with([
    'below the minimum' => ['0.99', false],
    'the minimum' => ['1.00', true],
    'in between' => ['250.00', true],
    'the maximum' => ['30000.00', true],
    'above the maximum' => ['30000.01', false],
    'zero' => ['0.00', false],
]);

it('stops on a setting that is not a number instead of charging a wrong fee', function (): void {
    config(['giving.platform_fee_rate' => 'one percent']);

    expect(fn () => $this->fees->platformFee(Money::ghs('100.00')))->toThrow(InvalidArgumentException::class, 'giving.platform_fee_rate');
});

it('gives the limits and the fee as a percentage for the page to show', function (): void {
    expect($this->fees->minimumGift()->amount)->toBe('1.00')
        ->and($this->fees->maximumGift()->amount)->toBe('30000.00')
        ->and($this->fees->collectionFeePercent())->toBe('1.99');

    config(['giving.collection_fee_rate' => '0.015']);
    expect($this->fees->collectionFeePercent())->toBe('1.50');
});
