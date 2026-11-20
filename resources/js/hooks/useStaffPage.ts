import { useSearchParams } from 'react-router';

/**
 * The staff list's page, kept in the address bar so a refresh and a shared link show the same page.
 */
export function useStaffPage() {
    const [params, setParams] = useSearchParams();
    const page = Math.max(1, Number(params.get('page')) || 1);

    function setPage(next: number) {
        setParams((current) => {
            current.set('page', String(next));

            return current;
        });
    }

    return { page, setPage };
}
