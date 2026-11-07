import { useSearchParams } from 'react-router';
import { monthsFrom } from '@/lib/reportMonths';

/** The member growth report's one choice, how many months to show, kept in the address bar so a refresh and a shared link agree. */
export function useMemberGrowthFilters() {
    const [params, setParams] = useSearchParams();

    function setMonths(value: string) {
        setParams(
            (current) => {
                current.set('months', value);

                return current;
            },
            { replace: true },
        );
    }

    return { months: monthsFrom(params.get('months')), setMonths };
}
