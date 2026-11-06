import { IDEMPOTENCY_HEADER } from '@/lib/idempotencyKey';
import { api } from '@/services';
import type { Page } from '@/types/members';
import type { StaffChange, StaffInviteInput, StaffMember } from '@/types/staff';

export const staffApi = {
    list: (page: number): Promise<Page<StaffMember>> => api.get<Page<StaffMember>>('/staff', { params: { page } }).then((r) => r.data),

    /** Invites someone and emails them a code. The key makes a double click or a retry send one invitation (docs/08, Idempotency). */
    invite: (input: StaffInviteInput, idempotencyKey: string): Promise<StaffMember> =>
        api.post<{ data: StaffMember }>('/staff', input, { headers: { [IDEMPOTENCY_HEADER]: idempotencyKey } }).then((r) => r.data.data),

    /** Changes name, phone and role. The server records only what really changed. */
    update: (id: string, input: Partial<StaffChange>): Promise<StaffMember> =>
        api.put<{ data: StaffMember }>(`/staff/${id}`, input).then((r) => r.data.data),

    /** Deactivates or reactivates. Deactivating signs the person out at once. */
    setActive: (id: string, isActive: boolean): Promise<StaffMember> =>
        api.patch<{ data: StaffMember }>(`/staff/${id}/status`, { is_active: isActive }).then((r) => r.data.data),
};
