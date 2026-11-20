import { useSearchParams } from 'react-router';
import { monthsFrom } from '@/lib/reportMonths';
import type { GivingFilters } from '@/types/reports';

/** What a fund id looks like (a ULID), so a made-up value in the address is ignored rather than sent. */
const FUND_ID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

/**
 * The giving report's choices, kept in the address bar so a refresh and a shared link show the same chart. A value that is
 * not one of the choices is ignored. The months choice is the one the member growth tab uses too.
 */
export function useGivingFilters() {
    const [params, setParams] = useSearchParams();
    const fund = params.get('fund') ?? '';

    const filters: GivingFilters = {
        months: monthsFrom(params.get('months')),
        fundId: FUND_ID.test(fund) ? fund : '',
    };

    function setFilter(name: 'months' | 'fund', value: string) {
        setParams(
            (current) => {
                if (value === '') current.delete(name);
                else current.set(name, value);

                return current;
            },
            { replace: true },
        );
    }

    return { filters, setFilter };
}
