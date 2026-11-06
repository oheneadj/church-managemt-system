import { useSearchParams } from 'react-router';
import { PLEDGE_STATUSES } from '@/constants/enums';
import type { PledgeFilters } from '@/types/giving';

/**
 * The pledge list's filters and page, kept in the address bar so a refresh and a shared link show the same list. A
 * value that is not valid is ignored. Changing a filter returns to page 1.
 */
export function usePledgeFilters() {
    const [params, setParams] = useSearchParams();

    const filters: PledgeFilters = {
        status: PLEDGE_STATUSES.find((option) => option.value === params.get('status'))?.value ?? '',
        fund: params.get('fund') ?? '',
        page: Math.max(1, Number(params.get('page')) || 1),
    };

    function setFilter(name: 'status' | 'fund', value: string) {
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
