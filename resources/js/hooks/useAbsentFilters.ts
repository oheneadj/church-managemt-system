import { useSearchParams } from 'react-router';
import { ABSENCE_THRESHOLD_MAX, ABSENCE_THRESHOLD_MIN } from '@/constants/settings';
import type { AbsentFilters } from '@/types/services';

/** The number of services in a row from the address bar, or null (the church's own setting) when it is missing or out of range. */
function consecutiveParam(value: string | null): number | null {
    const number = Number(value);

    return value !== null && Number.isInteger(number) && number >= ABSENCE_THRESHOLD_MIN && number <= ABSENCE_THRESHOLD_MAX ? number : null;
}

/**
 * The absent members list's choice and page, kept in the address bar so a refresh and a shared link show the same list.
 * Changing the number of services returns to page 1; an empty choice means the church's own setting.
 */
export function useAbsentFilters() {
    const [params, setParams] = useSearchParams();
    const filters: AbsentFilters = {
        consecutive: consecutiveParam(params.get('consecutive')),
        page: Math.max(1, Number(params.get('page')) || 1),
    };

    function setConsecutive(value: string) {
        setParams((current) => {
            if (value === '') current.delete('consecutive');
            else current.set('consecutive', value);
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

    return { filters, setConsecutive, setPage };
}
