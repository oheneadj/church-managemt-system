import type { MemberFilters } from '@/types/members';

/**
 * Every TanStack Query key in one place. A key lists everything that changes the result, and keys are
 * nested so invalidating `members.all` refreshes every member query at once.
 */
export const QUERY_KEYS = {
    members: {
        all: ['members'] as const,
        list: (filters: MemberFilters) => ['members', 'list', filters] as const,
        detail: (id: string) => ['members', 'detail', id] as const,
    },
    families: {
        all: ['families'] as const,
        list: (search: string) => ['families', 'list', { search }] as const,
        detail: (id: string) => ['families', 'detail', id] as const,
    },
    departments: {
        all: ['departments'] as const,
    },
} as const;
