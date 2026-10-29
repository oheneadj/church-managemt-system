import { useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { fundTypesApi } from '@/services/api/fundTypes';

/** The funds a gift or pledge can be given to. They rarely change, so they are kept for a few minutes. */
export function useFundTypes() {
    return useQuery({
        queryKey: QUERY_KEYS.fundTypes.active,
        queryFn: () => fundTypesApi.active(),
        staleTime: 5 * 60_000,
    });
}
