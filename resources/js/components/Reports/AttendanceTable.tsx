import type { AttendanceReport } from '@/types/reports';
import { formatDate } from '@/utils/formatDate';

/**
 * The attendance figures as a table: the same numbers as the chart, for anyone who prefers to read them or cannot see the
 * chart. Newest first, as the server sends them.
 */
export function AttendanceTable({ report }: { report: AttendanceReport }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <caption className="sr-only">Attendance, newest first</caption>
                {report.group_by === 'week' ? (
                    <>
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th scope="col" className="py-2 pr-4 font-medium">
                                    Week of
                                </th>
                                <th scope="col" className="py-2 pr-4 text-right font-medium">
                                    People
                                </th>
                                <th scope="col" className="py-2 pr-4 text-right font-medium">
                                    First-timers
                                </th>
                                <th scope="col" className="py-2 text-right font-medium">
                                    Services
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.data.map((row) => (
                                <tr key={row.week} className="border-b">
                                    <th scope="row" className="py-2 pr-4 font-normal">
                                        {formatDate(row.week_start)}
                                    </th>
                                    <td className="py-2 pr-4 text-right tabular-nums">{row.total}</td>
                                    <td className="py-2 pr-4 text-right tabular-nums">{row.first_timers}</td>
                                    <td className="py-2 text-right tabular-nums">{row.services}</td>
                                </tr>
                            ))}
                        </tbody>
                    </>
                ) : (
                    <>
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th scope="col" className="py-2 pr-4 font-medium">
                                    Date
                                </th>
                                <th scope="col" className="py-2 pr-4 font-medium">
                                    Service
                                </th>
                                <th scope="col" className="py-2 text-right font-medium">
                                    People
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.data.map((row) => (
                                <tr key={row.service_id} className="border-b">
                                    <th scope="row" className="py-2 pr-4 font-normal">
                                        {formatDate(row.service_date)}
                                    </th>
                                    <td className="py-2 pr-4">{row.service_name}</td>
                                    <td className="py-2 text-right tabular-nums">{row.total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </>
                )}
            </table>
        </div>
    );
}
