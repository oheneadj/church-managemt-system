import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { contributionsApi } from '@/services/api/contributions';
import type { ContributionFilters } from '@/types/giving';

/** One page of gifts for the filters. The previous page stays on screen while the next loads. */
export function useContributions(filters: ContributionFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.contributions.list(filters),
        queryFn: () => contributionsApi.list(filters),
        placeholderData: keepPreviousData,
    });
}

/**
 * Voiding a gift. It changes the gift list, any pledge the gift paid, the offering reports and the giving totals on a
 * member's profile, so it refreshes all of those.
 */
export function useContributionMutations() {
    const queryClient = useQueryClient();

    return {
        voidGift: useMutation({
            mutationFn: ({ id, reason }: { id: string; reason: string }) => contributionsApi.void(id, reason),
            onSuccess: () =>
                Promise.all([
                    queryClient.invalidateQueries({ queryKey: QUERY_KEYS.contributions.all }),
                    queryClient.invalidateQueries({ queryKey: QUERY_KEYS.pledges.all }),
                    queryClient.invalidateQueries({ queryKey: QUERY_KEYS.members.all }),
                    queryClient.invalidateQueries({ queryKey: QUERY_KEYS.services.all }),
                ]),
        }),
    };
}
