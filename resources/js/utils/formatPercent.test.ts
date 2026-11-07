import { describe, expect, it } from 'vitest';
import { formatPercent } from '@/utils/formatPercent';

describe('formatPercent', () => {
    it('shows the sign and one decimal place', () => {
        expect(formatPercent(9.1)).toBe('+9.1%');
        expect(formatPercent(-66.7)).toBe('-66.7%');
        expect(formatPercent(12)).toBe('+12.0%');
    });

    it('shows no sign for no change', () => {
        expect(formatPercent(0)).toBe('0.0%');
    });
});
