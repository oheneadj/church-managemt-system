import { useQuery } from '@tanstack/react-query';
import { ACTIVITY_STALE_MS } from '@/constants/dashboard';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { activityApi } from '@/services/api/activity';

/** The latest lines of activity. Asked for again when the person comes back to the page, once it is a little old. */
export function useRecentActivity(count: number) {
    return useQuery({
        queryKey: QUERY_KEYS.activity.recent(count),
        queryFn: () => activityApi.recent(count),
        staleTime: ACTIVITY_STALE_MS,
    });
}
