import { describe, expect, it } from 'vitest';
import { memberGrowthPoints } from '@/components/Reports/memberGrowthPoints';
import type { MemberGrowthReport } from '@/types/reports';

const report: MemberGrowthReport = {
    data: [
        { month: '2026-10', month_start: '2026-10-01', new: 4, lost: 1, net: 3, active_total: 212 },
        { month: '2026-09', month_start: '2026-09-01', new: 0, lost: 0, net: 0, active_total: 209 },
        { month: '2026-08', month_start: '2026-08-01', new: 1, lost: 5, net: -4, active_total: 209 },
    ],
    summary: { new: 5, lost: 6, net: -1 },
};

describe('memberGrowthPoints', () => {
    it('puts the oldest month on the left, because the server sends the newest first', () => {
        expect(memberGrowthPoints(report).map((point) => point.id)).toEqual(['2026-08', '2026-09', '2026-10']);
    });

    it('draws new members above the baseline and lost ones below it, and keeps the figures as the server counted them', () => {
        const october = memberGrowthPoints(report)[2];

        expect(october?.parts).toEqual([
            { seriesKey: 'new', value: 4, text: '4' },
            { seriesKey: 'lost', value: -1, text: '1' },
        ]);
    });

    it('names a month by its net change with the sign, and says how many were active at its end', () => {
        const [august, , october] = memberGrowthPoints(report);

        expect(october).toMatchObject({
            label: 'Oct 26',
            title: 'October 2026',
            totalText: 'Net +3',
            detail: '212 active at the end of the month',
        });
        expect(august?.totalText).toBe('Net -4');
    });

    it('keeps a quiet month as a column of nothing that says "Net 0"', () => {
        const september = memberGrowthPoints(report)[1];

        expect(september?.totalText).toBe('Net 0');
        expect(september?.parts.every((part) => part.value === 0)).toBe(true);
    });
});
