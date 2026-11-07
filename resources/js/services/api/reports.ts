import { api } from '@/services';
import type { AttendanceFilters, AttendanceReport } from '@/types/reports';

export const reportsApi = {
    /** The attendance trend for the last `weeks` weeks, by week or by service, newest first (docs/08, Attendance Trends). */
    attendance: (filters: AttendanceFilters): Promise<AttendanceReport> =>
        api
            .get<AttendanceReport>('/reports/attendance', { params: { weeks: filters.weeks, group_by: filters.groupBy } })
            .then((r) => r.data),
};
