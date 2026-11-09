import { useEffect, useRef, useState } from 'react';

/** How an action stands in a demonstration: not started, running, or finished. */
export type SimulatedState = 'idle' | 'working' | 'done';

/**
 * A pretend action for the showcase: `run()` starts it and it finishes by itself after `durationMs`, then calls `onDone` if given. It lets the page show how a
 * control behaves while work is under way without calling any server.
 */
export function useSimulatedAction(durationMs: number, onDone?: () => void) {
    const [state, setState] = useState<SimulatedState>('idle');
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const finished = useRef(onDone);

    // The latest callback is the one called when the pretend work ends.
    useEffect(() => {
        finished.current = onDone;
    });

    useEffect(
        () => () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const run = () => {
        setState('working');
        timer.current = setTimeout(() => {
            setState('done');
            finished.current?.();
        }, durationMs);
    };

    const reset = () => setState('idle');

    return { state, run, reset };
}
