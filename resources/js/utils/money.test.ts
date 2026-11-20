import { describe, expect, it } from 'vitest';
import { moneyToPesewas } from '@/utils/money';

describe('moneyToPesewas', () => {
    it('reads whole cedis and one or two decimals exactly', () => {
        expect(moneyToPesewas('200')).toBe(20000n);
        expect(moneyToPesewas('200.5')).toBe(20050n);
        expect(moneyToPesewas('0.07')).toBe(7n);
        expect(moneyToPesewas('999999.99')).toBe(99999999n);
    });

    it('does not lose a pesewa on amounts that floating point gets wrong', () => {
        expect(moneyToPesewas('0.1')).toBe(10n);
        expect(moneyToPesewas('4.35')).toBe(435n);
        expect(moneyToPesewas('1234567890.12')).toBe(123456789012n);
    });

    it('refuses anything that is not an amount', () => {
        for (const text of ['', 'abc', '-5', '1,000', '1.234', '1.', '.5', '1e3', '12 .00']) {
            expect(moneyToPesewas(text)).toBeNull();
        }
    });

    it('ignores spaces around the amount', () => {
        expect(moneyToPesewas(' 50.00 ')).toBe(5000n);
    });
});
