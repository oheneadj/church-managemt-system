import { describe, expect, it } from 'vitest';
import { amountField } from '@/components/Giving/amountField';

function message(text: string): string | undefined {
    const result = amountField('1000.00', 'pledge').safeParse(text);

    return result.success ? undefined : result.error.issues[0]?.message;
}

describe('amountField', () => {
    it.each(['1', '0.01', '200.5', '1000', '1000.00'])('accepts "%s"', (text) => {
        expect(message(text)).toBeUndefined();
    });

    it('asks for an amount when it is empty', () => {
        expect(message('')).toBe('Enter the amount');
        expect(message('   ')).toBe('Enter the amount');
    });

    it.each(['abc', '-5', '1,000', '10.123', '1e3'])('refuses "%s" as not an amount in cedis', (text) => {
        expect(message(text)).toBe('Enter an amount in cedis, with at most two decimals');
    });

    it('refuses nothing', () => {
        expect(message('0')).toBe('The amount must be more than 0');
        expect(message('0.00')).toBe('The amount must be more than 0');
    });

    it('refuses more than the largest amount, by a single pesewa, and names what it is for', () => {
        expect(message('1000.01')).toBe('One pledge can be at most 1000.00');
    });
});
