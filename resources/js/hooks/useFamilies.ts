import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { familiesApi, type FamilyInput, type FamilyListParams } from '@/services/api/families';

/** One page of families, optionally narrowed by a name search. The previous page stays on screen while the next loads. */
export function useFamilies(params: FamilyListParams) {
    return useQuery({
        queryKey: QUERY_KEYS.families.list(params),
        queryFn: () => familiesApi.list(params),
        placeholderData: keepPreviousData,
    });
}

/** Every family of the church, for pickers such as the one on the member form. */
export function useAllFamilies() {
    return useQuery({
        queryKey: QUERY_KEYS.families.picker,
        queryFn: familiesApi.listAll,
    });
}

/** One family with the members the signed-in user may see. */
export function useFamily(id: string) {
    return useQuery({
        queryKey: QUERY_KEYS.families.detail(id),
        queryFn: () => familiesApi.get(id),
    });
}

/**
 * Creating and renaming families. A change also refreshes the members, because a family's head and
 * member list show on the member records too.
 */
export function useFamilyMutations() {
    const queryClient = useQueryClient();
    const refresh = async () => {
        await queryClient.invalidateQueries({ queryKey: QUERY_KEYS.families.all });
        await queryClient.invalidateQueries({ queryKey: QUERY_KEYS.members.all });
    };

    return {
        create: useMutation({ mutationFn: (input: FamilyInput) => familiesApi.create(input), onSuccess: refresh }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<Pick<FamilyInput, 'name' | 'notes'>> }) =>
                familiesApi.update(id, input),
            onSuccess: refresh,
        }),
    };
}
