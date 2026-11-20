import { api } from '@/services';
import type { Page } from '@/types/members';
import type { AbsentFilters, AbsentMember, AttendanceRecorded, ServiceAttendance, Visitor, VisitorInput } from '@/types/services';

export const attendanceApi = {
    /** One page of the people checked in to a service, with its headcount. */
    forService: (serviceId: string, page: number): Promise<ServiceAttendance> =>
        api.get<ServiceAttendance>(`/services/${serviceId}/attendance`, { params: { page } }).then((r) => r.data),

    /** Marks these members present by hand. */
    record: (serviceId: string, memberIds: string[]): Promise<AttendanceRecorded> =>
        api.post<AttendanceRecorded>(`/services/${serviceId}/attendance`, { member_ids: memberIds, method: 'manual' }).then((r) => r.data),

    captureVisitor: (serviceId: string, input: VisitorInput): Promise<Visitor> =>
        api.post<{ data: Visitor }>(`/services/${serviceId}/visitors`, input).then((r) => r.data.data),

    /** Active members who missed the last few Sunday services in a row. Without `consecutive` the server uses the church's own setting. */
    absent: (filters: AbsentFilters): Promise<Page<AbsentMember>> =>
        api
            .get<Page<AbsentMember>>('/attendance/absent', {
                params: { page: filters.page, ...(filters.consecutive === null ? {} : { consecutive: filters.consecutive }) },
            })
            .then((r) => r.data),
};
