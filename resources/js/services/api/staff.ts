import { IDEMPOTENCY_HEADER } from '@/lib/idempotencyKey';
import { api } from '@/services';
import type { Page } from '@/types/members';
import type { StaffChange, StaffInviteInput, StaffMember, StaffName, StaffPermissionChange } from '@/types/staff';

/** The largest page the API allows; a church has few staff, so one call loads them all. */
const DIRECTORY_PAGE_SIZE = 100;

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

    /** Gives or takes away extra permissions on top of the role. It applies on the person's next request. */
    setPermissions: (id: string, change: StaffPermissionChange): Promise<StaffMember> =>
        api.put<{ data: StaffMember }>(`/staff/${id}/permissions`, change).then((r) => r.data.data),

    /** The church's active staff by name, for choosing one. Open to staff who may edit members; it holds no email or phone. */
    directory: (): Promise<StaffName[]> =>
        api.get<Page<StaffName>>('/staff/directory', { params: { per_page: DIRECTORY_PAGE_SIZE } }).then((r) => r.data.data),
};
