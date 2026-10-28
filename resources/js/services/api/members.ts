import { api } from '@/services';
import type { Member, MemberFilters, MemberInput, MemberSummary, Milestone, MilestoneInput, Page } from '@/types/members';
import type { MemberStatus } from '@/constants/enums';

/** Turns the filters into query parameters, leaving out the ones that are empty. */
function toQuery(filters: MemberFilters): Record<string, string | number> {
    const query: Record<string, string | number> = { page: filters.page };

    if (filters.search !== '') query.search = filters.search;
    if (filters.status !== '') query.status = filters.status;
    if (filters.gender !== '') query.gender = filters.gender;
    if (filters.department_id !== '') query.department_id = filters.department_id;

    return query;
}

export const membersApi = {
    list: (filters: MemberFilters): Promise<Page<MemberSummary>> =>
        api.get<Page<MemberSummary>>('/members', { params: toQuery(filters) }).then((r) => r.data),

    get: (id: string): Promise<Member> => api.get<{ data: Member }>(`/members/${id}`).then((r) => r.data.data),

    create: (input: MemberInput): Promise<Member> => api.post<{ data: Member }>('/members', input).then((r) => r.data.data),

    update: (id: string, input: Partial<MemberInput>): Promise<Member> =>
        api.put<{ data: Member }>(`/members/${id}`, input).then((r) => r.data.data),

    changeStatus: (id: string, status: MemberStatus, note: string | null): Promise<Member> =>
        api.patch<{ data: Member }>(`/members/${id}/status`, { status, note }).then((r) => r.data.data),

    /** Sends the photo as a file upload and returns the new signed link. */
    uploadPhoto: (id: string, photo: File): Promise<string> => {
        const body = new FormData();
        body.append('photo', photo);

        return api.post<{ photo_url: string }>(`/members/${id}/photo`, body).then((r) => r.data.photo_url);
    },

    /** Everything held about the member, for a subject access request. The shape is the JSON the server sends. */
    exportData: (id: string): Promise<unknown> => api.get<unknown>(`/members/${id}/data-export`).then((r) => r.data),

    /** Erases the member's personal data. The record stays but it cannot be undone. */
    erase: (id: string, reason: string): Promise<void> => api.post(`/members/${id}/erasure`, { reason }).then(() => undefined),

    addMilestone: (id: string, input: MilestoneInput): Promise<Milestone> =>
        api.post<{ data: Milestone }>(`/members/${id}/milestones`, input).then((r) => r.data.data),
};
