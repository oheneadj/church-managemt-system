import { useSearchParams } from 'react-router';
import { PAYMENT_METHODS } from '@/constants/enums';
import type { ContributionFilters } from '@/types/giving';

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/** A date from the address bar, or empty when it is missing or not a date. */
function dateParam(value: string | null): string {
    return value !== null && ISO_DATE.test(value) ? value : '';
}

/**
 * The gift list's filters and page, kept in the address bar so a refresh and a shared link show the same list. A value
 * that is not valid is ignored. Changing a filter returns to page 1.
 */
export function useContributionFilters() {
    const [params, setParams] = useSearchParams();

    const filters: ContributionFilters = {
        from: dateParam(params.get('from')),
        to: dateParam(params.get('to')),
        fund: params.get('fund') ?? '',
        method: PAYMENT_METHODS.find((option) => option.value === params.get('method'))?.value ?? '',
        voided: params.get('voided') === '1',
        page: Math.max(1, Number(params.get('page')) || 1),
    };

    function setFilter(name: 'from' | 'to' | 'fund' | 'method' | 'voided', value: string) {
        setParams((current) => {
            if (value === '') current.delete(name);
            else current.set(name, value);
            current.delete('page');

            return current;
        });
    }

    function setPage(page: number) {
        setParams((current) => {
            current.set('page', String(page));

            return current;
        });
    }

    return { filters, setFilter, setPage };
}
