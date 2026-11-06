import { describe, expect, it } from 'vitest';
import { formatDateTime } from '@/utils/formatDateTime';

describe('formatDateTime', () => {
    it('shows the day first, with the month in letters and the time', () => {
        expect(formatDateTime('2026-11-05T14:30:00Z')).toMatch(/^5 Nov 2026, \d{2}:\d{2}$/);
    });

    it('gives back what it cannot read', () => {
        expect(formatDateTime('not a date')).toBe('not a date');
    });
});
