import { describe, expect, it } from 'vitest';
import { givingPoints, givingSeries } from '@/components/Reports/givingPoints';
import { fundType, givingByMonth } from '@/test/fixtures';

/** The church's funds in display order: Tithe, Sunday Offering, Building Fund. */
const funds = [
    fundType({ id: 'fund-1', name: 'Tithe' }),
    fundType({ id: 'fund-2', name: 'Sunday Offering' }),
    fundType({ id: 'fund-3', name: 'Building Fund' }),
];

describe('givingSeries', () => {
    it('gives each fund that gave the colour of its place in the church list, and leaves out funds that gave nothing', () => {
        expect(givingSeries(funds, givingByMonth())).toEqual([
            { key: 'fund-1', name: 'Tithe', color: 'var(--viz-series-1)' },
            { key: 'fund-3', name: 'Building Fund', color: 'var(--viz-series-3)' },
        ]);
    });

    it('keeps a fund its colour when the report is narrowed to that fund, so filtering never repaints it', () => {
        const onlyBuilding = givingByMonth({
            data: [
                {
                    month: '2026-10',
                    month_start: '2026-10-01',
                    total: '7450.00',
                    total_formatted: 'GHS 7,450.00',
                    by_fund: [{ fund_type_id: 'fund-3', name: 'Building Fund', total: '7450.00', total_formatted: 'GHS 7,450.00' }],
                },
            ],
        });

        expect(givingSeries(funds, onlyBuilding)).toEqual([{ key: 'fund-3', name: 'Building Fund', color: 'var(--viz-series-3)' }]);
    });

    it('shares one grey called "Other funds" among the funds past the five colours', () => {
        const many = Array.from({ length: 7 }, (_, index) => fundType({ id: `f${index}`, name: `Fund ${index}` }));
        const report = givingByMonth({
            data: [
                {
                    month: '2026-10',
                    month_start: '2026-10-01',
                    total: '70.00',
                    total_formatted: 'GHS 70.00',
                    by_fund: many.map((fund) => ({ fund_type_id: fund.id, name: fund.name, total: '10.00', total_formatted: 'GHS 10.00' })),
                },
            ],
        });

        const series = givingSeries(many, report);

        expect(series).toHaveLength(6);
        expect(series.at(-1)).toEqual({ key: 'other-funds', name: 'Other funds', color: 'var(--viz-other)' });
        expect(givingPoints(many, report)[0]?.parts.map((part) => part.seriesKey)).toEqual([
            'f0',
            'f1',
            'f2',
            'f3',
            'f4',
            'other-funds',
            'other-funds',
        ]);
    });

    it('still draws a fund that was switched off and so is not in the church list, after the listed ones', () => {
        const report = givingByMonth({
            data: [
                {
                    month: '2026-10',
                    month_start: '2026-10-01',
                    total: '10.00',
                    total_formatted: 'GHS 10.00',
                    by_fund: [{ fund_type_id: 'old', name: 'Old Fund', total: '10.00', total_formatted: 'GHS 10.00' }],
                },
            ],
        });

        expect(givingSeries(funds, report)).toEqual([{ key: 'old', name: 'Old Fund', color: 'var(--viz-series-4)' }]);
    });
});

describe('givingPoints', () => {
    it('puts the oldest month on the left, because the server sends the newest first', () => {
        expect(givingPoints(funds, givingByMonth()).map((point) => point.id)).toEqual(['2026-08', '2026-09', '2026-10']);
    });

    it('titles a month in full and labels it short, and shows the total the server formatted', () => {
        const october = givingPoints(funds, givingByMonth())[2];

        expect(october).toMatchObject({ label: 'Oct 26', title: 'October 2026', totalText: 'GHS 15,450.00' });
    });

    it('lists each fund as a block with its formatted amount, in colour order, and never adds the amounts up', () => {
        const october = givingPoints(funds, givingByMonth())[2];

        expect(october?.parts).toEqual([
            { seriesKey: 'fund-1', value: 8000, text: 'GHS 8,000.00', name: 'Tithe' },
            { seriesKey: 'fund-3', value: 7450, text: 'GHS 7,450.00', name: 'Building Fund' },
        ]);
    });

    it('stacks the funds in colour order whatever order the server lists them in, so a fund is always at the same height in the stack', () => {
        const backwards = givingByMonth({
            data: [
                {
                    month: '2026-10',
                    month_start: '2026-10-01',
                    total: '15450.00',
                    total_formatted: 'GHS 15,450.00',
                    by_fund: [
                        { fund_type_id: 'fund-3', name: 'Building Fund', total: '7450.00', total_formatted: 'GHS 7,450.00' },
                        { fund_type_id: 'fund-1', name: 'Tithe', total: '8000.00', total_formatted: 'GHS 8,000.00' },
                    ],
                },
            ],
        });

        expect(givingPoints(funds, backwards)[0]?.parts.map((part) => part.seriesKey)).toEqual(['fund-1', 'fund-3']);
    });

    it('keeps a month with nothing given as an empty column, not a gap', () => {
        expect(givingPoints(funds, givingByMonth())[0]).toMatchObject({ id: '2026-08', totalText: 'GHS 0.00', parts: [] });
    });
});
