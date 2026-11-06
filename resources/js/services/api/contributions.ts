import { IDEMPOTENCY_HEADER } from '@/lib/idempotencyKey';
import { api } from '@/services';
import type { Contribution, ContributionFilters, ContributionInput } from '@/types/giving';
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

    /** Records a cash gift. The key makes a double click or a retry record one gift (docs/08, Idempotency). */
    record: (input: ContributionInput, idempotencyKey: string): Promise<Contribution> =>
        api
            .post<{ data: Contribution }>('/contributions', input, { headers: { [IDEMPOTENCY_HEADER]: idempotencyKey } })
            .then((r) => r.data.data),

    /** Voids a gift with the reason the person gave. The gift stays on record, marked. */
    void: (id: string, reason: string): Promise<Contribution> =>
        api.patch<{ data: Contribution }>(`/contributions/${id}/void`, { reason }).then((r) => r.data.data),
};
