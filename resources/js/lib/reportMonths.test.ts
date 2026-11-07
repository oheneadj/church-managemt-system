import { describe, expect, it } from 'vitest';
import { monthsFrom } from '@/lib/reportMonths';

describe('monthsFrom', () => {
    it('reads one of the offered choices', () => {
        expect(monthsFrom('6')).toBe(6);
        expect(monthsFrom('24')).toBe(24);
    });

    it('falls back to 12 for anything else, including nothing', () => {
        expect(monthsFrom(null)).toBe(12);
        expect(monthsFrom('500')).toBe(12);
        expect(monthsFrom('soon')).toBe(12);
    });
});
