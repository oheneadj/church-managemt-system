import { useSearchParams } from 'react-router';

/**
 * The current step of a multi-step form, kept in the URL (`?step=2`, counting from 1) so a refresh keeps the
 * user on the same step. It returns a zero-based index, clamped to the real steps, and a setter.
 */
export function useUrlStep(totalSteps: number): [number, (index: number) => void] {
    const [params, setParams] = useSearchParams();
    const requested = Number(params.get('step')) || 1;
    const index = Math.min(Math.max(requested, 1), totalSteps) - 1;

    function setIndex(next: number) {
        setParams(
            (current) => {
                current.set('step', String(next + 1));

                return current;
            },
            { replace: true },
        );
    }

    return [index, setIndex];
}
