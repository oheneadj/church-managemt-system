import { describe, expect, it } from 'vitest';
import { formatMonth } from '@/utils/formatMonth';

describe('formatMonth', () => {
    it('gives a short form for an axis and a long form for a title', () => {
        expect(formatMonth('2026-10', 'short')).toBe('Oct 26');
        expect(formatMonth('2026-10', 'long')).toBe('October 2026');
    });

    it('accepts the first day of the month too, and keeps the first and last months of a year', () => {
        expect(formatMonth('2026-01-01', 'long')).toBe('January 2026');
        expect(formatMonth('2026-12', 'short')).toBe('Dec 26');
    });

    it('returns text that is not a month unchanged', () => {
        expect(formatMonth('soon', 'short')).toBe('soon');
    });
});
