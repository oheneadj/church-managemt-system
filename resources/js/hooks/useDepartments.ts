import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { departmentsApi } from '@/services/api/departments';
import type { DepartmentInput } from '@/types/members';

/** The church's active departments, for filters and pickers. They change rarely, so they are kept for ten minutes. */
export function useDepartments() {
    return useQuery({
        queryKey: QUERY_KEYS.departments.all,
        queryFn: departmentsApi.list,
        staleTime: 10 * 60_000,
    });
}

/** Every department including the retired ones, for managing them. */
export function useAllDepartments() {
    return useQuery({ queryKey: QUERY_KEYS.departments.manage, queryFn: () => departmentsApi.all() });
}

/**
 * Adding, changing and retiring departments. Each refreshes every list of departments (the filters and pickers too) and the
 * member lists and records, which show each member's departments by name.
 */
export function useDepartmentMutations() {
    const queryClient = useQueryClient();
    const refresh = () => {
        void queryClient.invalidateQueries({ queryKey: QUERY_KEYS.departments.all });
        void queryClient.invalidateQueries({ queryKey: QUERY_KEYS.members.all });
    };

    return {
        create: useMutation({ mutationFn: (input: DepartmentInput) => departmentsApi.create(input), onSuccess: refresh }),

        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<DepartmentInput> }) => departmentsApi.update(id, input),
            onSuccess: refresh,
        }),

        setActive: useMutation({
            mutationFn: ({ id, isActive }: { id: string; isActive: boolean }) => departmentsApi.setActive(id, isActive),
            onSuccess: refresh,
        }),
    };
}
