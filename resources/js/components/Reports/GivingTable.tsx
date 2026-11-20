import { NO_FIGURE } from '@/constants/reports';
import type { GivingReport } from '@/types/reports';
import { formatMonth } from '@/utils/formatMonth';

/**
 * The giving figures as a table: one row for each month, newest first, and a column for every fund that gave in the period
 * (including the ones the chart folds into "Other funds"). The amounts are the texts the server formatted.
 */
export function GivingTable({ report }: { report: GivingReport }) {
    const funds = new Map(report.data.flatMap((row) => row.by_fund.map((fund) => [fund.fund_type_id, fund.name] as const)));

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <caption className="sr-only">Giving by month, newest first</caption>
                <thead>
                    <tr className="border-b text-muted-foreground">
                        <th scope="col" className="py-2 pr-4 font-medium">
                            Month
                        </th>
                        <th scope="col" className="py-2 pr-4 text-right font-medium">
                            Total
                        </th>
                        {[...funds].map(([id, name]) => (
                            <th key={id} scope="col" className="py-2 pr-4 text-right font-medium whitespace-nowrap">
                                {name}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {report.data.map((row) => (
                        <tr key={row.month} className="border-b">
                            <th scope="row" className="py-2 pr-4 font-normal whitespace-nowrap">
                                {formatMonth(row.month_start, 'long')}
                            </th>
                            <td className="py-2 pr-4 text-right whitespace-nowrap tabular-nums">{row.total_formatted}</td>
                            {[...funds.keys()].map((id) => (
                                <td key={id} className="py-2 pr-4 text-right whitespace-nowrap tabular-nums">
                                    {row.by_fund.find((fund) => fund.fund_type_id === id)?.total_formatted ?? NO_FIGURE}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
