import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { serviceSchedulesApi } from '@/services/api/serviceSchedules';
import { servicesApi } from '@/services/api/services';
import { pageOf, serviceSchedule } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }));

const respond = (data: unknown) => Promise.resolve({ data });

describe('serviceSchedulesApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists a page of schedules', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([serviceSchedule()])));

        expect((await serviceSchedulesApi.list(2)).data[0]?.name).toBe('Sunday Morning Service');
        expect(api.get).toHaveBeenCalledWith('/service-schedules', { params: { page: 2 } });
    });

    it('sets a schedule up with the idempotency key in the header', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ data: serviceSchedule() }));
        const input = { name: 'Sunday Morning Service', type: 'sunday', weekday: 'sunday', starts_at: '09:00', ends_at: null } as const;

        await serviceSchedulesApi.create(input, 'key-0000-1111');

        expect(api.post).toHaveBeenCalledWith('/service-schedules', input, { headers: { 'Idempotency-Key': 'key-0000-1111' } });
    });

    it('changes a schedule', async () => {
        vi.mocked(api.put).mockReturnValue(respond({ data: serviceSchedule({ is_active: false }) }));

        const result = await serviceSchedulesApi.update('schedule-1', { is_active: false });

        expect(result.is_active).toBe(false);
        expect(api.put).toHaveBeenCalledWith('/service-schedules/schedule-1', { is_active: false });
    });
});

describe('servicesApi.cancel', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks the server to cancel the service and gives back nothing', async () => {
        vi.mocked(api.delete).mockReturnValue(Promise.resolve({ data: '' }));

        await expect(servicesApi.cancel('service-1')).resolves.toBeUndefined();
        expect(api.delete).toHaveBeenCalledWith('/services/service-1');
    });
});
