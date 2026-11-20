import { useMutation } from '@tanstack/react-query';
import { exportsApi } from '@/services/api/exports';
import type { ContributionFilters } from '@/types/giving';
import type { MemberFilters } from '@/types/members';

/** Asking for the member list as a CSV, with the filters now on screen. Nothing on screen changes: the link arrives by email. */
export function useExportMembers(filters: MemberFilters) {
    return useMutation({ mutationFn: () => exportsApi.members(filters) });
}

/** Asking for the gifts as a CSV, with the filters now on screen. Nothing on screen changes: the link arrives by email. */
export function useExportContributions(filters: ContributionFilters) {
    return useMutation({ mutationFn: () => exportsApi.contributions(filters) });
}
