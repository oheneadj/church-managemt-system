import type { ContributionFilters } from '@/types/giving';
import type { MemberFilters } from '@/types/members';

/**
 * The member list's filters as query parameters, leaving out the empty ones. The list and the CSV export both take exactly
 * these (the export reads no page), so they are built in one place and a file always holds what the screen showed.
 */
export function memberFilterParams(filters: MemberFilters): Record<string, string> {
    const params: Record<string, string> = {};

    if (filters.search !== '') params.search = filters.search;
    if (filters.status !== '') params.status = filters.status;
    if (filters.gender !== '') params.gender = filters.gender;
    if (filters.department_id !== '') params.department_id = filters.department_id;

    return params;
}

/**
 * The gift list's filters as the parameters both the list and the CSV export take, leaving out the empty ones. The voided
 * switch is the list's own: the export always includes voided gifts, flagged, so it can be audited (D-064).
 */
export function contributionFilterParams(filters: ContributionFilters): Record<string, string> {
    const params: Record<string, string> = {};

    if (filters.from !== '') params.from = filters.from;
    if (filters.to !== '') params.to = filters.to;
    if (filters.fund !== '') params.fund_type_id = filters.fund;
    if (filters.method !== '') params.payment_method = filters.method;

    return params;
}
