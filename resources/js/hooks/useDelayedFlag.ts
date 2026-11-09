import { useEffect, useState } from 'react';

/**
 * Turns true only once `active` has stayed true for `delayMs`, and turns false at once when `active` ends. It lets a loading
 * indicator skip work that finishes quickly. The timer is the one outside system this syncs with, so an effect is right here.
 */
export function useDelayedFlag(active: boolean, delayMs: number): boolean {
    const [elapsed, setElapsed] = useState(false);

    useEffect(() => {
        if (!active) {
            return;
        }

        const timer = setTimeout(() => setElapsed(true), delayMs);

        return () => {
            clearTimeout(timer);
            setElapsed(false);
        };
    }, [active, delayMs]);

    return active && elapsed;
}
