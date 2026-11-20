import { afterEach, describe, expect, it, vi } from 'vitest';
import { daysFromToday, today } from '@/utils/today';

describe('today', () => {
    afterEach(() => vi.useRealTimers());

    it('writes the local date as YYYY-MM-DD with leading zeros', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 0, 5, 23, 30));

        expect(today()).toBe('2026-01-05');
    });

    it('counts days from today, forwards and back, across a month and a year end', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 11, 30, 12, 0));

        expect(daysFromToday(0)).toBe('2026-12-30');
        expect(daysFromToday(3)).toBe('2027-01-02');
        expect(daysFromToday(-30)).toBe('2026-11-30');
    });
});
