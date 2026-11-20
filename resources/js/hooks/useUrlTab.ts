import { useSearchParams } from 'react-router';

/**
 * The selected tab, kept in the URL (`?tab=giving`) so a refresh and a shared link open the same tab.
 * An unknown value falls back to the first tab.
 */
export function useUrlTab<T extends string>(tabs: readonly T[]): [T, (tab: string) => void] {
    const [params, setParams] = useSearchParams();
    const requested = params.get('tab');
    const selected = tabs.find((tab) => tab === requested) ?? tabs[0];

    function select(tab: string) {
        setParams(
            (current) => {
                current.set('tab', tab);

                return current;
            },
            { replace: true },
        );
    }

    return [selected as T, select];
}
