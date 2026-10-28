import { useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { departmentsApi } from '@/services/api/departments';

/** The church's active departments, for filters and pickers. They change rarely, so they are kept for ten minutes. */
export function useDepartments() {
    return useQuery({
        queryKey: QUERY_KEYS.departments.all,
        queryFn: departmentsApi.list,
        staleTime: 10 * 60_000,
    });
}
