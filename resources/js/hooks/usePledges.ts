import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { pledgesApi } from '@/services/api/pledges';
import type { PledgeChange, PledgeFilters, PledgeInput } from '@/types/giving';

/** One page of pledges for the filters. The previous page stays on screen while the next loads. */
export function usePledges(filters: PledgeFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.pledges.list(filters),
        queryFn: () => pledgesApi.list(filters),
        placeholderData: keepPreviousData,
    });
}

/** Making and changing pledges. Either one refreshes every pledge list. */
export function usePledgeMutations() {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: QUERY_KEYS.pledges.all });

    return {
        create: useMutation({
            mutationFn: ({ input, idempotencyKey }: { input: PledgeInput; idempotencyKey: string }) =>
                pledgesApi.create(input, idempotencyKey),
            onSuccess: refresh,
        }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<PledgeChange> }) => pledgesApi.update(id, input),
            onSuccess: refresh,
        }),
    };
}
