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
        list: (params: { search: string; page: number }) => ['families', 'list', params] as const,
        picker: ['families', 'picker'] as const,
        detail: (id: string) => ['families', 'detail', id] as const,
    },
    contributions: {
        all: ['contributions'] as const,
        list: (filters: { from: string; to: string; fund: string; method: string; voided: boolean; page: number }) =>
            ['contributions', 'list', filters] as const,
    },
    pledges: {
        all: ['pledges'] as const,
    },
    fundTypes: {
        all: ['fund-types'] as const,
        active: ['fund-types', 'active'] as const,
    },
    serviceSchedules: {
        all: ['service-schedules'] as const,
        list: (page: number) => ['service-schedules', 'list', page] as const,
    },
    services: {
        all: ['services'] as const,
        list: (filters: { type: string; page: number }) => ['services', 'list', filters] as const,
        detail: (id: string) => ['services', 'detail', id] as const,
        qr: (id: string) => ['services', 'qr', id] as const,
        attendance: (id: string, page: number) => ['services', 'attendance', id, page] as const,
    },
    departments: {
        all: ['departments'] as const,
    },
} as const;
