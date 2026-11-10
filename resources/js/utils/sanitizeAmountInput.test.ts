import { describe, expect, it } from 'vitest';
import { sanitizeAmountInput } from '@/utils/money';

describe('sanitizeAmountInput', () => {
    it.each([
        ['20.00', '20.00'],
        ['20', '20'],
        ['20.5', '20.5'],
        ['', ''],
        ['.5', '.5'],
        ['20.', '20.'],
    ])('keeps a valid amount as it is: "%s"', (typed, kept) => {
        expect(sanitizeAmountInput(typed)).toBe(kept);
    });

    it.each([
        ['abc', ''],
        ['2a0', '20'],
        ['1e5', '15'],
        ['-20', '20'],
        ['+20', '20'],
        ['GHS 20', '20'],
        ['1,200.50', '1200.50'],
        ['20 .5', '20.5'],
    ])('drops everything that is not a digit or a point: "%s"', (typed, kept) => {
        expect(sanitizeAmountInput(typed)).toBe(kept);
    });

    it('keeps only the first decimal point', () => {
        expect(sanitizeAmountInput('1.2.3')).toBe('1.23');
        expect(sanitizeAmountInput('..5')).toBe('.5');
    });

    it('keeps at most two decimals', () => {
        expect(sanitizeAmountInput('10.555')).toBe('10.55');
        expect(sanitizeAmountInput('0.999')).toBe('0.99');
    });
});
