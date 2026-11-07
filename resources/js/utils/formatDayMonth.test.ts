import { describe, expect, it } from 'vitest';
import { formatDayMonth } from '@/utils/formatDayMonth';

describe('formatDayMonth', () => {
    it('shows a calendar date as day and short month', () => {
        expect(formatDayMonth('2026-10-12')).toBe('12 Oct');
        expect(formatDayMonth('2026-01-01')).toBe('1 Jan');
    });

    it('returns text that is not a date unchanged', () => {
        expect(formatDayMonth('soon')).toBe('soon');
    });
});
