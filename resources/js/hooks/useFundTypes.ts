import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { fundTypesApi } from '@/services/api/fundTypes';
import type { FundTypeInput } from '@/types/giving';

/** The funds a gift or pledge can be given to. They rarely change, so they are kept for a few minutes. */
export function useFundTypes() {
    return useQuery({
        queryKey: QUERY_KEYS.fundTypes.active,
        queryFn: () => fundTypesApi.active(),
        staleTime: 5 * 60_000,
    });
}

/** Every fund including the ones switched off, for managing them. */
export function useAllFundTypes() {
    return useQuery({ queryKey: QUERY_KEYS.fundTypes.manage, queryFn: () => fundTypesApi.all() });
}

/**
 * Adding, changing and switching funds on or off. Each refreshes every list of funds (the pickers on the gift and pledge
 * forms too) and the giving reports, which show each fund under its current name.
 */
export function useFundTypeMutations() {
    const queryClient = useQueryClient();
    const refresh = () => {
        void queryClient.invalidateQueries({ queryKey: QUERY_KEYS.fundTypes.all });
        void queryClient.invalidateQueries({ queryKey: QUERY_KEYS.reports.all });
    };

    return {
        create: useMutation({ mutationFn: (input: FundTypeInput) => fundTypesApi.create(input), onSuccess: refresh }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<FundTypeInput> }) => fundTypesApi.update(id, input),
            onSuccess: refresh,
        }),

        setActive: useMutation({
            mutationFn: ({ id, isActive }: { id: string; isActive: boolean }) => fundTypesApi.setActive(id, isActive),
            onSuccess: refresh,
        }),
    };
}
