import { IDEMPOTENCY_HEADER } from '@/lib/idempotencyKey';
import { api } from '@/services';
import type { Page } from '@/types/members';
import type { ServiceSchedule, ServiceScheduleInput } from '@/types/services';

export const serviceSchedulesApi = {
    list: (page: number): Promise<Page<ServiceSchedule>> =>
        api.get<Page<ServiceSchedule>>('/service-schedules', { params: { page } }).then((r) => r.data),

    /** Sets a schedule up and makes its first weeks of services. The key makes a double click set up one schedule. */
    create: (input: ServiceScheduleInput, idempotencyKey: string): Promise<ServiceSchedule> =>
        api
            .post<{ data: ServiceSchedule }>('/service-schedules', input, { headers: { [IDEMPOTENCY_HEADER]: idempotencyKey } })
            .then((r) => r.data.data),

    update: (id: string, input: Partial<ServiceScheduleInput>): Promise<ServiceSchedule> =>
        api.put<{ data: ServiceSchedule }>(`/service-schedules/${id}`, input).then((r) => r.data.data),
};
