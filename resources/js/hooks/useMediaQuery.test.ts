import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useMediaQuery } from '@/hooks/useMediaQuery';

/** A media query list whose answer can be changed by the test, telling listeners when it is. */
function fakeMedia(initial: boolean) {
    let matches = initial;
    const listeners = new Set<() => void>();
    const list = {
        get matches() {
            return matches;
        },
        addEventListener: (_event: string, listener: () => void) => listeners.add(listener),
        removeEventListener: (_event: string, listener: () => void) => listeners.delete(listener),
    };
    const matchMedia = vi.fn(() => list as unknown as MediaQueryList);

    return {
        matchMedia,
        listeners,
        set(next: boolean) {
            matches = next;
            listeners.forEach((listener) => listener());
        },
    };
}

describe('useMediaQuery', () => {
    const original = window.matchMedia;
    afterEach(() => {
        window.matchMedia = original;
    });

    it('answers what the screen matches now, and asks with the query it was given', () => {
        const media = fakeMedia(true);
        window.matchMedia = media.matchMedia;

        const { result } = renderHook(() => useMediaQuery('(min-width: 768px)'));

        expect(result.current).toBe(true);
        expect(media.matchMedia).toHaveBeenCalledWith('(min-width: 768px)');
    });

    it('follows the screen as it changes', () => {
        const media = fakeMedia(false);
        window.matchMedia = media.matchMedia;
        const { result } = renderHook(() => useMediaQuery('(min-width: 768px)'));
        expect(result.current).toBe(false);

        act(() => media.set(true));
        expect(result.current).toBe(true);

        act(() => media.set(false));
        expect(result.current).toBe(false);
    });

    it('lets go of its listener when the page goes away', () => {
        const media = fakeMedia(false);
        window.matchMedia = media.matchMedia;
        const { unmount } = renderHook(() => useMediaQuery('(min-width: 768px)'));
        expect(media.listeners.size).toBe(1);

        unmount();

        expect(media.listeners.size).toBe(0);
    });
});
