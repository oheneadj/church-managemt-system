import { afterEach, describe, expect, it, vi } from 'vitest';
import { today } from '@/utils/today';

describe('today', () => {
    afterEach(() => vi.useRealTimers());

    it('writes the local date as YYYY-MM-DD with leading zeros', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 0, 5, 23, 30));

        expect(today()).toBe('2026-01-05');
    });
});
