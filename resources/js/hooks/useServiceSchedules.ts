import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { serviceSchedulesApi } from '@/services/api/serviceSchedules';
import type { ServiceScheduleInput } from '@/types/services';

/** One page of the church's weekly schedules. Pass `enabled: false` for someone who may not see them, so no call is made. */
export function useServiceSchedules(page: number, enabled = true) {
    return useQuery({
        queryKey: QUERY_KEYS.serviceSchedules.list(page),
        queryFn: () => serviceSchedulesApi.list(page),
        placeholderData: keepPreviousData,
        enabled,
    });
}

/**
 * Setting up and changing schedules. Both can make or move services, so they refresh the schedule list and every
 * service query.
 */
export function useServiceScheduleMutations() {
    const queryClient = useQueryClient();
    const refresh = () =>
        Promise.all([
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.serviceSchedules.all }),
            queryClient.invalidateQueries({ queryKey: QUERY_KEYS.services.all }),
        ]);

    return {
        create: useMutation({
            mutationFn: ({ input, idempotencyKey }: { input: ServiceScheduleInput; idempotencyKey: string }) =>
                serviceSchedulesApi.create(input, idempotencyKey),
            onSuccess: refresh,
        }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<ServiceScheduleInput> }) => serviceSchedulesApi.update(id, input),
            onSuccess: refresh,
        }),
    };
}
