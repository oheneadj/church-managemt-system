import { api } from '@/services';
import type { Family, Page } from '@/types/members';

/** The largest page the API allows; family and department pickers load everything in one call. */
const PICKER_PAGE_SIZE = 100;

export interface FamilyInput {
    name: string;
    notes?: string | null;
    head_member_id?: string | null;
}

export const familiesApi = {
    list: (search: string): Promise<Page<Family>> =>
        api
            .get<Page<Family>>('/families', { params: { per_page: PICKER_PAGE_SIZE, ...(search === '' ? {} : { search }) } })
            .then((r) => r.data),

    get: (id: string): Promise<Family> => api.get<{ data: Family }>(`/families/${id}`).then((r) => r.data.data),

    create: (input: FamilyInput): Promise<Family> => api.post<{ data: Family }>('/families', input).then((r) => r.data.data),

    update: (id: string, input: Partial<Pick<FamilyInput, 'name' | 'notes'>>): Promise<Family> =>
        api.put<{ data: Family }>(`/families/${id}`, input).then((r) => r.data.data),
};
