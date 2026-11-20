import { api } from '@/services';
import { contributionFilterParams, memberFilterParams } from '@/services/api/filterParams';
import type { ContributionFilters } from '@/types/giving';
import type { ExportQueued } from '@/types/exports';
import type { MemberFilters } from '@/types/members';

export const exportsApi = {
    /** Asks for the member list, narrowed by the filters, as a CSV. The server queues it and emails the person a link (docs/08, D-064). */
    members: (filters: MemberFilters): Promise<ExportQueued> =>
        api.post<ExportQueued>('/exports/members', memberFilterParams(filters)).then((r) => r.data),

    /** Asks for the gifts, narrowed by the filters, as a CSV. Voided gifts are always included, flagged. */
    contributions: (filters: ContributionFilters): Promise<ExportQueued> =>
        api.post<ExportQueued>('/exports/contributions', contributionFilterParams(filters)).then((r) => r.data),
};
