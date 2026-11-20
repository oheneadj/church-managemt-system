import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { LIVE_CHECKIN_POLL_MS } from '@/constants/polling';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { attendanceApi } from '@/services/api/attendance';
import type { VisitorInput } from '@/types/services';

/**
 * Who has checked in to a service, with the headcount. When `live` is set (the service is open) it asks again every
 * ten seconds, so the screen at the door stays current. The previous page stays on screen while the next loads.
 */
export function useServiceAttendance(serviceId: string, page: number, live: boolean) {
    return useQuery({
        queryKey: QUERY_KEYS.services.attendance(serviceId, page),
        queryFn: () => attendanceApi.forService(serviceId, page),
        placeholderData: keepPreviousData,
        refetchInterval: live ? LIVE_CHECKIN_POLL_MS : false,
    });
}

/**
 * Marking members present by hand and adding a visitor. Both refresh the attendance and the service's count straight away,
 * instead of waiting for the next ten-second poll.
 */
export function useAttendanceMutations(serviceId: string) {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: QUERY_KEYS.services.all });

    return {
        record: useMutation({ mutationFn: (memberIds: string[]) => attendanceApi.record(serviceId, memberIds), onSuccess: refresh }),

        captureVisitor: useMutation({
            mutationFn: (input: VisitorInput) => attendanceApi.captureVisitor(serviceId, input),
            onSuccess: refresh,
        }),
    };
}
