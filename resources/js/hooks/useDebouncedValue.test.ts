import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';

describe('useDebouncedValue', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('starts with the value it is given', () => {
        const { result } = renderHook(() => useDebouncedValue('a', 500));

        expect(result.current).toBe('a');
    });

    it('follows a change only after it has stopped changing', () => {
        const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 500), { initialProps: { value: 'a' } });

        rerender({ value: 'ab' });
        act(() => vi.advanceTimersByTime(300));
        rerender({ value: 'abc' });
        act(() => vi.advanceTimersByTime(300));
        expect(result.current).toBe('a');

        act(() => vi.advanceTimersByTime(200));
        expect(result.current).toBe('abc');
    });
});
