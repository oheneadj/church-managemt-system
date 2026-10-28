import { api } from '@/services';
import type { Family, Page } from '@/types/members';

/** The largest page the API allows; pickers use it so they need as few requests as possible. */
const LARGEST_PAGE = 100;

export interface FamilyInput {
    name: string;
    notes?: string | null;
    head_member_id?: string | null;
}

export interface FamilyListParams {
    search: string;
    page: number;
}

/** Asks for one page of families; empty search text is left out of the request. */
function fetchPage(page: number, search: string, perPage?: number): Promise<Page<Family>> {
    return api
        .get<Page<Family>>('/families', {
            params: { page, ...(perPage === undefined ? {} : { per_page: perPage }), ...(search === '' ? {} : { search }) },
        })
        .then((r) => r.data);
}

export const familiesApi = {
    list: ({ search, page }: FamilyListParams): Promise<Page<Family>> => fetchPage(page, search),

    /** Every family of the church, for pickers. A big church has more than one page, so it reads them all. */
    listAll: async (): Promise<Family[]> => {
        const first = await fetchPage(1, '', LARGEST_PAGE);
        const families = [...first.data];

        for (let page = 2; page <= first.meta.last_page; page++) {
            families.push(...(await fetchPage(page, '', LARGEST_PAGE)).data);
        }

        return families;
    },

    get: (id: string): Promise<Family> => api.get<{ data: Family }>(`/families/${id}`).then((r) => r.data.data),

    create: (input: FamilyInput): Promise<Family> => api.post<{ data: Family }>('/families', input).then((r) => r.data.data),

    update: (id: string, input: Partial<Pick<FamilyInput, 'name' | 'notes'>>): Promise<Family> =>
        api.put<{ data: Family }>(`/families/${id}`, input).then((r) => r.data.data),
};
