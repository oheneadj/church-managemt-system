import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { attendanceApi } from '@/services/api/attendance';
import type { AbsentFilters } from '@/types/services';

/** One page of the members who have gone absent. The previous page stays on screen while the next loads. */
export function useAbsentMembers(filters: AbsentFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.absentMembers.list(filters),
        queryFn: () => attendanceApi.absent(filters),
        placeholderData: keepPreviousData,
    });
}
