import { api } from '@/services';
import type { OfferingReport } from '@/types/giving';

export const offeringReportApi = {
    /** What one service received, by fund and payment method. It needs `giving.summary` and holds totals only. */
    get: (serviceId: string): Promise<OfferingReport> =>
        api.get<{ data: OfferingReport }>(`/services/${serviceId}/offering-report`).then((r) => r.data.data),
};
