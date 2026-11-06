import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { staffApi } from '@/services/api/staff';
import type { StaffChange, StaffInviteInput } from '@/types/staff';

/** One page of the church's staff. The previous page stays on screen while the next loads. */
export function useStaff(page: number) {
    return useQuery({
        queryKey: QUERY_KEYS.staff.list(page),
        queryFn: () => staffApi.list(page),
        placeholderData: keepPreviousData,
    });
}

/** Inviting, changing, and switching staff on or off. Each one refreshes the staff list. */
export function useStaffMutations() {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: QUERY_KEYS.staff.all });

    return {
        invite: useMutation({
            mutationFn: ({ input, idempotencyKey }: { input: StaffInviteInput; idempotencyKey: string }) =>
                staffApi.invite(input, idempotencyKey),
            onSuccess: refresh,
        }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<StaffChange> }) => staffApi.update(id, input),
            onSuccess: refresh,
        }),

        setActive: useMutation({
            mutationFn: ({ id, isActive }: { id: string; isActive: boolean }) => staffApi.setActive(id, isActive),
            onSuccess: refresh,
        }),
    };
}
