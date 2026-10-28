import { useSearchParams } from 'react-router';
import { SERVICE_TYPES } from '@/constants/enums';
import type { ServiceFilters } from '@/types/services';

/**
 * The service list's type filter and page number, kept in the URL so a refresh and a shared link show the same list.
 * An unknown type in the URL is ignored. Changing the type returns to page 1.
 */
export function useServiceFilters() {
    const [params, setParams] = useSearchParams();
    const type = SERVICE_TYPES.find((option) => option.value === params.get('type'))?.value ?? '';

    const filters: ServiceFilters = { type, page: Math.max(1, Number(params.get('page')) || 1) };

    function setType(value: string) {
        setParams((current) => {
            if (value === '') current.delete('type');
            else current.set('type', value);
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

    return { filters, setType, setPage };
}
