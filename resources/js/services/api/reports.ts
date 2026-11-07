import { api } from '@/services';
import type { AttendanceFilters, AttendanceReport, GivingFilters, GivingReport } from '@/types/reports';

export const reportsApi = {
    /** The attendance trend for the last `weeks` weeks, by week or by service, newest first (docs/08, Attendance Trends). */
    attendance: (filters: AttendanceFilters): Promise<AttendanceReport> =>
        api
            .get<AttendanceReport>('/reports/attendance', { params: { weeks: filters.weeks, group_by: filters.groupBy } })
            .then((r) => r.data),

    /** Giving by month for the last `months` months, optionally for one fund, newest first (docs/08, Giving Trends). */
    giving: (filters: GivingFilters): Promise<GivingReport> =>
        api
            .get<GivingReport>('/reports/giving', {
                params: { months: filters.months, ...(filters.fundId === '' ? {} : { fund_type_id: filters.fundId }) },
            })
            .then((r) => r.data),
};
