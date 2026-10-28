import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { MemberStatus } from '@/constants/enums';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { membersApi } from '@/services/api/members';
import type { MemberInput, MilestoneInput } from '@/types/members';

/**
 * The ways to change a member. Each one refreshes every member query afterwards, so the directory
 * and the profile never show old data.
 */
export function useMemberMutations() {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: QUERY_KEYS.members.all });

    return {
        create: useMutation({ mutationFn: (input: MemberInput) => membersApi.create(input), onSuccess: refresh }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<MemberInput> }) => membersApi.update(id, input),
            onSuccess: refresh,
        }),

        changeStatus: useMutation({
            mutationFn: ({ id, status, note }: { id: string; status: MemberStatus; note: string | null }) =>
                membersApi.changeStatus(id, status, note),
            onSuccess: refresh,
        }),

        uploadPhoto: useMutation({
            mutationFn: ({ id, photo }: { id: string; photo: File }) => membersApi.uploadPhoto(id, photo),
            onSuccess: refresh,
        }),

        exportData: useMutation({ mutationFn: (id: string) => membersApi.exportData(id) }),

        erase: useMutation({
            mutationFn: ({ id, reason }: { id: string; reason: string }) => membersApi.erase(id, reason),
            onSuccess: refresh,
        }),

        addMilestone: useMutation({
            mutationFn: ({ id, input }: { id: string; input: MilestoneInput }) => membersApi.addMilestone(id, input),
            onSuccess: refresh,
        }),
    };
}
