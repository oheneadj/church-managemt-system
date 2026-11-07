import { describe, expect, it } from 'vitest';
import { formatCount, formatSignedCount } from '@/utils/formatCount';

describe('formatCount', () => {
    it('writes a whole number with thousands separators', () => {
        expect(formatCount(0)).toBe('0');
        expect(formatCount(420)).toBe('420');
        expect(formatCount(12500)).toBe('12,500');
    });
});

describe('formatSignedCount', () => {
    it('shows the sign of a change, and none for no change', () => {
        expect(formatSignedCount(3)).toBe('+3');
        expect(formatSignedCount(-2)).toBe('-2');
        expect(formatSignedCount(0)).toBe('0');
    });
});
