import { api } from '@/services';
import type { FundType, FundTypeInput } from '@/types/giving';
import type { Page } from '@/types/members';

/** The most funds one request asks for; a church has a handful, so one request holds them all. */
const ALL_FUNDS = 100;

export const fundTypesApi = {
    /** The funds that can be chosen for a new gift or pledge, in display order. */
    active: (): Promise<FundType[]> =>
        api.get<Page<FundType>>('/fund-types', { params: { is_active: 1, per_page: ALL_FUNDS } }).then((r) => r.data.data),

    /** Every fund, switched off ones included, in display order, for the screen that manages them. */
    all: (): Promise<FundType[]> => api.get<Page<FundType>>('/fund-types', { params: { per_page: ALL_FUNDS } }).then((r) => r.data.data),

    create: (input: FundTypeInput): Promise<FundType> => api.post<{ data: FundType }>('/fund-types', input).then((r) => r.data.data),

    /** Changes the fields sent. There is no delete: a fund no longer used is switched off. */
    update: (id: string, input: Partial<FundTypeInput>): Promise<FundType> =>
        api.put<{ data: FundType }>(`/fund-types/${id}`, input).then((r) => r.data.data),

    /** Switches a fund off or on. Past gifts keep pointing at it; it just cannot be chosen for new ones. */
    setActive: (id: string, isActive: boolean): Promise<FundType> =>
        api.patch<{ data: FundType }>(`/fund-types/${id}`, { is_active: isActive }).then((r) => r.data.data),
};
