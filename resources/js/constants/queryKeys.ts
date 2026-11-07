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
        list: (filters: { status: string; fund: string; page: number }) => ['pledges', 'list', filters] as const,
        forGift: (memberId: string, fundId: string) => ['pledges', 'for-gift', memberId, fundId] as const,
    },
    fundTypes: {
        all: ['fund-types'] as const,
        active: ['fund-types', 'active'] as const,
        manage: ['fund-types', 'manage'] as const,
    },
    serviceSchedules: {
        all: ['service-schedules'] as const,
        list: (page: number) => ['service-schedules', 'list', page] as const,
    },
    services: {
        all: ['services'] as const,
        list: (filters: { type: string; page: number }) => ['services', 'list', filters] as const,
        offeringReport: (id: string) => ['services', 'offering-report', id] as const,
        onDate: (date: string) => ['services', 'on-date', date] as const,
        detail: (id: string) => ['services', 'detail', id] as const,
        qr: (id: string) => ['services', 'qr', id] as const,
        attendance: (id: string, page: number) => ['services', 'attendance', id, page] as const,
    },
    staff: {
        all: ['staff'] as const,
        list: (page: number) => ['staff', 'list', page] as const,
    },
    church: {
        credits: ['church', 'credits'] as const,
        profile: ['church', 'profile'] as const,
        settings: ['church', 'settings'] as const,
    },
    messages: {
        all: ['messages'] as const,
        list: (filters: { channel: string; type: string; from: string; to: string; page: number }) =>
            ['messages', 'list', filters] as const,
        estimate: (input: { message: string; status: string; department: string }) => ['messages', 'estimate', input] as const,
    },
    absentMembers: {
        list: (filters: { consecutive: number | null; page: number }) => ['absent-members', 'list', filters] as const,
    },
    reports: {
        all: ['reports'] as const,
        attendance: (filters: { weeks: number; groupBy: string }) => ['reports', 'attendance', filters] as const,
        giving: (filters: { months: number; fundId: string }) => ['reports', 'giving', filters] as const,
        memberGrowth: (months: number) => ['reports', 'member-growth', months] as const,
    },
    departments: {
        all: ['departments'] as const,
        manage: ['departments', 'manage'] as const,
    },
} as const;
