import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { servicesApi } from '@/services/api/services';
import type { ServiceFilters, ServiceInput } from '@/types/services';

/** One page of services, optionally of one type. The previous page stays on screen while the next loads. */
export function useServices(filters: ServiceFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.services.list(filters),
        queryFn: () => servicesApi.list(filters),
        placeholderData: keepPreviousData,
    });
}

/** One service with its attendance count. */
export function useService(id: string) {
    return useQuery({
        queryKey: QUERY_KEYS.services.detail(id),
        queryFn: () => servicesApi.get(id),
    });
}

/**
 * Creating and changing services, and opening or closing check-in. Every change refreshes all service queries, so the
 * list and the service page never disagree.
 */
export function useServiceMutations() {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: QUERY_KEYS.services.all });

    return {
        create: useMutation({ mutationFn: (input: ServiceInput) => servicesApi.create(input), onSuccess: refresh }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<ServiceInput> }) => servicesApi.update(id, input),
            onSuccess: refresh,
        }),

        setCheckin: useMutation({
            mutationFn: ({ id, isOpen }: { id: string; isOpen: boolean }) => servicesApi.setCheckin(id, isOpen),
            onSuccess: refresh,
        }),
    };
}
