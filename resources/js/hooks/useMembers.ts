import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { membersApi } from '@/services/api/members';
import type { MemberFilters } from '@/types/members';

/**
 * One page of the member directory for the given filters. Pass `enabled = false` to wait (a picker that has nothing to search for yet). While the next page loads, the previous
 * page stays on screen (`isPlaceholderData` tells the page it is not final yet).
 */
export function useMembers(filters: MemberFilters, enabled = true) {
    return useQuery({
        queryKey: QUERY_KEYS.members.list(filters),
        queryFn: () => membersApi.list(filters),
        placeholderData: keepPreviousData,
        enabled,
    });
}

/** One member's full record. An empty id (the add screen) makes no request. */
export function useMember(id: string) {
    return useQuery({
        queryKey: QUERY_KEYS.members.detail(id),
        queryFn: () => membersApi.get(id),
        enabled: id !== '',
    });
}
