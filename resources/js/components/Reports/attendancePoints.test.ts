import { describe, expect, it } from 'vitest';
import { attendancePoints } from '@/components/Reports/attendancePoints';
import type { AttendanceReport } from '@/types/reports';

const summary = { average: 121, highest: 156, lowest: 98 };

describe('attendancePoints', () => {
    const weeks: AttendanceReport = {
        group_by: 'week',
        summary,
        data: [
            { week: '2026-W43', week_start: '2026-10-19', total: 0, first_timers: 0, services: 0 },
            { week: '2026-W42', week_start: '2026-10-12', total: 124, first_timers: 3, services: 2 },
            { week: '2026-W41', week_start: '2026-10-05', total: 98, first_timers: 1, services: 1 },
        ],
    };

    it('puts the oldest week on the left, because the server sends the newest first', () => {
        expect(attendancePoints(weeks).map((point) => point.id)).toEqual(['2026-W41', '2026-W42', '2026-W43']);
    });

    it('labels a week by its Monday and says what it holds in the tooltip', () => {
        const [, middle] = attendancePoints(weeks);

        expect(middle).toEqual({
            id: '2026-W42',
            label: '12 Oct',
            title: 'Week of 12 Oct 2026',
            totalText: '124 people',
            peakText: '124',
            parts: [{ seriesKey: 'people', value: 124, text: '124' }],
            detail: '2 services · 3 first-timers',
        });
    });

    it('keeps a week with no service as a column of 0 that says so, and uses the singular for one', () => {
        const points = attendancePoints(weeks);

        expect(points[2]).toMatchObject({ totalText: '0 people', detail: 'No service recorded' });
        expect(points[2]?.parts[0]?.value).toBe(0);
        expect(points[0]?.detail).toBe('1 service · 1 first-timer');
    });

    it('names each service with its date when grouped by service', () => {
        const byService: AttendanceReport = {
            group_by: 'service',
            summary,
            data: [
                { service_id: 's2', service_date: '2026-10-18', service_name: 'Sunday Morning Service', total: 118 },
                { service_id: 's1', service_date: '2026-10-11', service_name: 'Midweek Prayer', total: 40 },
            ],
        };

        const points = attendancePoints(byService);

        expect(points.map((point) => [point.id, point.label, point.title, point.totalText])).toEqual([
            ['s1', '11 Oct', 'Midweek Prayer, 11 Oct 2026', '40 people'],
            ['s2', '18 Oct', 'Sunday Morning Service, 18 Oct 2026', '118 people'],
        ]);
    });
});
