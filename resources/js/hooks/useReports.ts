import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { reportsApi } from '@/services/api/reports';
import type { AttendanceFilters, GivingFilters } from '@/types/reports';

/** The attendance trend. The previous figures stay on screen, dimmed, while a new choice loads. */
export function useAttendanceReport(filters: AttendanceFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.reports.attendance(filters),
        queryFn: () => reportsApi.attendance(filters),
        placeholderData: keepPreviousData,
    });
}

/** Giving by month. The previous figures stay on screen, dimmed, while a new choice loads. */
export function useGivingReport(filters: GivingFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.reports.giving(filters),
        queryFn: () => reportsApi.giving(filters),
        placeholderData: keepPreviousData,
    });
}

/** Membership change by month. The previous figures stay on screen, dimmed, while a new choice loads. */
export function useMemberGrowthReport(months: number) {
    return useQuery({
        queryKey: QUERY_KEYS.reports.memberGrowth(months),
        queryFn: () => reportsApi.memberGrowth(months),
        placeholderData: keepPreviousData,
    });
}
