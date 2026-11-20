import { IDEMPOTENCY_HEADER } from '@/lib/idempotencyKey';
import { GIFT_PLEDGES_PER_PAGE } from '@/constants/giving';
import { api } from '@/services';
import type { Pledge, PledgeChange, PledgeFilters, PledgeInput } from '@/types/giving';
import type { Page } from '@/types/members';

export const pledgesApi = {
    list: (filters: PledgeFilters): Promise<Page<Pledge>> =>
        api
            .get<Page<Pledge>>('/pledges', {
                params: {
                    page: filters.page,
                    ...(filters.status === '' ? {} : { status: filters.status }),
                    ...(filters.fund === '' ? {} : { fund_type_id: filters.fund }),
                },
            })
            .then((r) => r.data),

    /** One member's pledges to one fund, for choosing which one a gift pays. A member has few, so one page is enough. */
    forGift: (memberId: string, fundId: string): Promise<Page<Pledge>> =>
        api
            .get<Page<Pledge>>('/pledges', { params: { member_id: memberId, fund_type_id: fundId, per_page: GIFT_PLEDGES_PER_PAGE } })
            .then((r) => r.data),

    /** Makes a pledge. The key makes a double click or a retry make one pledge (docs/08, Idempotency). */
    create: (input: PledgeInput, idempotencyKey: string): Promise<Pledge> =>
        api.post<{ data: Pledge }>('/pledges', input, { headers: { [IDEMPOTENCY_HEADER]: idempotencyKey } }).then((r) => r.data.data),

    /** Changes the description, amount or target date. The paid amount and status are worked out by the server again. */
    update: (id: string, input: Partial<PledgeChange>): Promise<Pledge> =>
        api.put<{ data: Pledge }>(`/pledges/${id}`, input).then((r) => r.data.data),
};
