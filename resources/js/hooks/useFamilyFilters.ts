import { useSearchParams } from 'react-router';

/**
 * The family list's search text and page number, kept in the URL so a refresh and a shared link show the
 * same list. Searching returns to page 1.
 */
export function useFamilyFilters() {
    const [params, setParams] = useSearchParams();

    const filters = {
        search: params.get('search') ?? '',
        page: Math.max(1, Number(params.get('page')) || 1),
    };

    function setSearch(text: string) {
        setParams((current) => {
            if (text === '') current.delete('search');
            else current.set('search', text);
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

    return { filters, setSearch, setPage };
}
