import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useDelayedFlag } from '@/hooks/useDelayedFlag';

describe('useDelayedFlag', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('stays false until the delay has passed, then turns true', () => {
        const { result } = renderHook(() => useDelayedFlag(true, 400));

        expect(result.current).toBe(false);
        act(() => vi.advanceTimersByTime(399));
        expect(result.current).toBe(false);
        act(() => vi.advanceTimersByTime(1));
        expect(result.current).toBe(true);
    });

    it('never turns true for work that ends inside the delay', () => {
        const { result, rerender } = renderHook(({ active }) => useDelayedFlag(active, 400), { initialProps: { active: true } });

        act(() => vi.advanceTimersByTime(300));
        rerender({ active: false });
        act(() => vi.advanceTimersByTime(1000));

        expect(result.current).toBe(false);
    });

    it('turns false at once when the work ends, and starts the delay again for the next piece of work', () => {
        const { result, rerender } = renderHook(({ active }) => useDelayedFlag(active, 400), { initialProps: { active: true } });
        act(() => vi.advanceTimersByTime(400));
        expect(result.current).toBe(true);

        rerender({ active: false });
        expect(result.current).toBe(false);

        rerender({ active: true });
        expect(result.current).toBe(false);
        act(() => vi.advanceTimersByTime(400));
        expect(result.current).toBe(true);
    });

    it('is false when never active', () => {
        const { result } = renderHook(() => useDelayedFlag(false, 400));

        act(() => vi.advanceTimersByTime(1000));

        expect(result.current).toBe(false);
    });
});
