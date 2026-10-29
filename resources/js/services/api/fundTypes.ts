import { api } from '@/services';
import type { FundType } from '@/types/giving';
import type { Page } from '@/types/members';

/** The most funds one request asks for; a church has a handful, so one request holds them all. */
const ALL_FUNDS = 100;

export const fundTypesApi = {
    /** The funds that can be chosen for a new gift or pledge, in display order. */
    active: (): Promise<FundType[]> =>
        api.get<Page<FundType>>('/fund-types', { params: { is_active: 1, per_page: ALL_FUNDS } }).then((r) => r.data.data),
};
