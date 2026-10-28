import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { attendanceApi } from '@/services/api/attendance';
import { servicesApi } from '@/services/api/services';
import { pageOf, recorded, service, serviceAttendance, visitor } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn() } }));

const respond = (data: unknown) => Promise.resolve({ data });

describe('servicesApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks for a page of services and leaves an empty type out of the request', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([service()])));

        await servicesApi.list({ type: '', page: 2 });
        await servicesApi.list({ type: 'midweek', page: 1 });

        expect(api.get).toHaveBeenNthCalledWith(1, '/services', { params: { page: 2 } });
        expect(api.get).toHaveBeenNthCalledWith(2, '/services', { params: { page: 1, type: 'midweek' } });
    });

    it('unwraps one service, creates, updates and sets check-in', async () => {
        vi.mocked(api.get).mockReturnValue(respond({ data: service() }));
        vi.mocked(api.post).mockReturnValue(respond({ data: service() }));
        vi.mocked(api.put).mockReturnValue(respond({ data: service() }));
        vi.mocked(api.patch).mockReturnValue(respond({ data: service({ is_open: true }) }));

        expect((await servicesApi.get('service-1')).name).toBe('Sunday Morning Service');
        await servicesApi.create({ name: 'X', type: 'sunday', service_date: '2026-10-25' });
        await servicesApi.update('service-1', { name: 'Y' });
        const opened = await servicesApi.setCheckin('service-1', true);

        expect(api.post).toHaveBeenCalledWith('/services', expect.objectContaining({ name: 'X' }));
        expect(api.put).toHaveBeenCalledWith('/services/service-1', { name: 'Y' });
        expect(api.patch).toHaveBeenCalledWith('/services/service-1/checkin', { is_open: true });
        expect(opened.is_open).toBe(true);
    });
});

describe('attendanceApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it("reads one page of a service's attendance", async () => {
        vi.mocked(api.get).mockReturnValue(respond(serviceAttendance()));

        const result = await attendanceApi.forService('service-1', 3);

        expect(api.get).toHaveBeenCalledWith('/services/service-1/attendance', { params: { page: 3 } });
        expect(result.summary.total).toBe(1);
    });

    it('marks members present by hand', async () => {
        vi.mocked(api.post).mockReturnValue(respond(recorded()));

        await attendanceApi.record('service-1', ['m1', 'm2']);

        expect(api.post).toHaveBeenCalledWith('/services/service-1/attendance', { member_ids: ['m1', 'm2'], method: 'manual' });
    });

    it('captures a visitor and unwraps the record', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ data: visitor() }));

        expect((await attendanceApi.captureVisitor('service-1', { name: 'Yaw Boateng' })).visit_count).toBe(1);
        expect(api.post).toHaveBeenCalledWith('/services/service-1/visitors', { name: 'Yaw Boateng' });
    });
});
