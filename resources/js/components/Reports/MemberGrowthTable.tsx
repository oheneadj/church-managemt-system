import type { MemberGrowthReport } from '@/types/reports';
import { formatCount, formatSignedCount } from '@/utils/formatCount';
import { formatMonth } from '@/utils/formatMonth';

/** The membership figures as a table, one row for each month, newest first: the same numbers as the chart and the active total. */
export function MemberGrowthTable({ report }: { report: MemberGrowthReport }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <caption className="sr-only">Membership by month, newest first</caption>
                <thead>
                    <tr className="border-b text-muted-foreground">
                        <th scope="col" className="py-2 pr-4 font-medium">
                            Month
                        </th>
                        <th scope="col" className="py-2 pr-4 text-right font-medium">
                            New
                        </th>
                        <th scope="col" className="py-2 pr-4 text-right font-medium">
                            Lost
                        </th>
                        <th scope="col" className="py-2 pr-4 text-right font-medium">
                            Net
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Active at the end
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {report.data.map((row) => (
                        <tr key={row.month} className="border-b">
                            <th scope="row" className="py-2 pr-4 font-normal whitespace-nowrap">
                                {formatMonth(row.month_start, 'long')}
                            </th>
                            <td className="py-2 pr-4 text-right tabular-nums">{formatCount(row.new)}</td>
                            <td className="py-2 pr-4 text-right tabular-nums">{formatCount(row.lost)}</td>
                            <td className="py-2 pr-4 text-right tabular-nums">{formatSignedCount(row.net)}</td>
                            <td className="py-2 text-right tabular-nums">{formatCount(row.active_total)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
