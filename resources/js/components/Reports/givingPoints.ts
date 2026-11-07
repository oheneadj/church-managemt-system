import type { ChartSeries, ColumnPart, ColumnPoint } from '@/components/Reports/ColumnChart';
import { FUND_SERIES_COLOURS, OTHER_FUNDS_COLOUR, OTHER_FUNDS_KEY, OTHER_FUNDS_NAME } from '@/constants/reports';
import type { FundType } from '@/types/giving';
import type { GivingReport } from '@/types/reports';
import { formatMonth } from '@/utils/formatMonth';

/**
 * The funds in the order that decides their colours: the church's own list of funds in display order, then any fund that
 * gave in this period but is no longer in that list (one that was switched off), in the order the report names them.
 */
function fundOrder(funds: readonly FundType[], report: GivingReport): string[] {
    const known = funds.map((fund) => fund.id);
    const unlisted = report.data.flatMap((row) => row.by_fund.map((fund) => fund.fund_type_id)).filter((id) => !known.includes(id));

    return [...known, ...new Set(unlisted)];
}

/** The series key a fund is drawn under: its own id while it has a colour of its own, otherwise the shared grey. */
function seriesKeyFor(fundId: string, order: readonly string[]): string {
    return order.indexOf(fundId) < FUND_SERIES_COLOURS.length ? fundId : OTHER_FUNDS_KEY;
}

/**
 * The legend and colours for the funds that gave in this period, in colour order. A fund keeps the colour of its place in
 * the church's list of funds, so filtering to one fund or switching the period never repaints the others. Funds past the
 * colours share one grey called "Other funds".
 */
export function givingSeries(funds: readonly FundType[], report: GivingReport): ChartSeries[] {
    const order = fundOrder(funds, report);
    const gave = new Map(report.data.flatMap((row) => row.by_fund.map((fund) => [fund.fund_type_id, fund.name] as const)));
    const series: ChartSeries[] = order.flatMap((id, place) => {
        const name = gave.get(id);

        return name !== undefined && place < FUND_SERIES_COLOURS.length
            ? [{ key: id, name, color: FUND_SERIES_COLOURS[place] ?? OTHER_FUNDS_COLOUR }]
            : [];
    });

    return order.some((id, place) => gave.has(id) && place >= FUND_SERIES_COLOURS.length)
        ? [...series, { key: OTHER_FUNDS_KEY, name: OTHER_FUNDS_NAME, color: OTHER_FUNDS_COLOUR }]
        : series;
}

/**
 * Turns the report into chart columns, oldest on the left. Each fund that gave is a block in its colour, in colour order;
 * the amounts shown are the formatted texts the server sent, never added up here. The numbers used for drawing are
 * read from the exact decimal strings and are only ever used for the height of a block.
 */
export function givingPoints(funds: readonly FundType[], report: GivingReport): ColumnPoint[] {
    const order = fundOrder(funds, report);

    return report.data
        .map((row): ColumnPoint => {
            const parts: ColumnPart[] = [...row.by_fund]
                .sort((a, b) => order.indexOf(a.fund_type_id) - order.indexOf(b.fund_type_id))
                .map((fund) => ({
                    seriesKey: seriesKeyFor(fund.fund_type_id, order),
                    value: Number(fund.total),
                    text: fund.total_formatted,
                    name: fund.name,
                }));

            return {
                id: row.month,
                label: formatMonth(row.month_start, 'short'),
                title: formatMonth(row.month_start, 'long'),
                totalText: row.total_formatted,
                parts,
            };
        })
        .reverse();
}
