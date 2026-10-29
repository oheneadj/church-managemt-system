import { api } from '@/services';
import type { Contribution, ContributionFilters } from '@/types/giving';
import type { Page } from '@/types/members';

export const contributionsApi = {
    list: (filters: ContributionFilters): Promise<Page<Contribution>> =>
        api
            .get<Page<Contribution>>('/contributions', {
                params: {
                    page: filters.page,
                    ...(filters.from === '' ? {} : { from: filters.from }),
                    ...(filters.to === '' ? {} : { to: filters.to }),
                    ...(filters.fund === '' ? {} : { fund_type_id: filters.fund }),
                    ...(filters.method === '' ? {} : { payment_method: filters.method }),
                    ...(filters.voided ? { is_voided: 1 } : {}),
                },
            })
            .then((r) => r.data),

    /** Voids a gift with the reason the person gave. The gift stays on record, marked. */
    void: (id: string, reason: string): Promise<Contribution> =>
        api.patch<{ data: Contribution }>(`/contributions/${id}/void`, { reason }).then((r) => r.data.data),
};
