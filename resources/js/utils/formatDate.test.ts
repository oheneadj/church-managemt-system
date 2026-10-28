import { describe, expect, it } from 'vitest';
import { formatDate } from '@/utils/formatDate';

describe('formatDate', () => {
    it('shows a calendar date as day, short month and year', () => {
        expect(formatDate('2023-04-15')).toBe('15 Apr 2023');
        expect(formatDate('2026-01-01')).toBe('1 Jan 2026');
    });

    it('keeps the same day whatever the timezone, including the first and last day of a year', () => {
        expect(formatDate('2024-12-31')).toBe('31 Dec 2024');
        expect(formatDate('2024-01-01')).toBe('1 Jan 2024');
    });

    it('ignores a time on the end, and returns text that is not a date unchanged', () => {
        expect(formatDate('2023-04-15T10:30:00Z')).toBe('15 Apr 2023');
        expect(formatDate('soon')).toBe('soon');
    });
});
