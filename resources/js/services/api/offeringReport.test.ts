import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { offeringReportApi } from '@/services/api/offeringReport';
import { offeringReport } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn() } }));

describe('offeringReportApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it("asks for one service's report and returns the report without its wrapper", async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { data: offeringReport() } });

        const report = await offeringReportApi.get('service-1');

        expect(api.get).toHaveBeenCalledWith('/services/service-1/offering-report');
        expect(report.day_grand_total_formatted).toBe('GHS 3,150.00');
    });
});
