import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { contributionsApi } from '@/services/api/contributions';
import type { ContributionFilters, ContributionInput } from '@/types/giving';

/** One page of gifts for the filters. The previous page stays on screen while the next loads. */
export function useContributions(filters: ContributionFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.contributions.list(filters),
        queryFn: () => contributionsApi.list(filters),
        placeholderData: keepPreviousData,
    });
}

/**
 * Recording or voiding a gift changes the gift list, any pledge the gift pays, the offering reports and the giving totals
 * on a member's profile, so both refresh all of those.
 */
function useRefreshAfterGiftChange() {
    const queryClient = useQueryClient();

    return () =>
        Promise.all([
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.contributions.all }),
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.pledges.all }),
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.members.all }),
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.services.all }),
            // A gift sends a receipt text, which uses credits.
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.church.credits }),
        ]);
}

/** Recording a cash gift and voiding a gift. */
export function useContributionMutations() {
    const refresh = useRefreshAfterGiftChange();

    return {
        record: useMutation({
            mutationFn: ({ input, idempotencyKey }: { input: ContributionInput; idempotencyKey: string }) =>
                contributionsApi.record(input, idempotencyKey),
            onSuccess: refresh,
        }),

        voidGift: useMutation({
            mutationFn: ({ id, reason }: { id: string; reason: string }) => contributionsApi.void(id, reason),
            onSuccess: refresh,
        }),
    };
}
