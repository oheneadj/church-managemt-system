import { CHECKIN_METHODS } from '@/constants/enums';
import type { AttendanceSummary } from '@/types/services';

interface AttendanceSummaryCardsProps {
    summary: AttendanceSummary;
}

/**
 * The headcount of a service: the big number, members and visitors apart, the share of active members who came,
 * and how people checked in. Screen readers hear the numbers change (the region is polite and live).
 */
export function AttendanceSummaryCards({ summary }: AttendanceSummaryCardsProps) {
    const usedMethods = CHECKIN_METHODS.filter((method) => summary.by_method[method.value] > 0);

    return (
        <section aria-label="Headcount" aria-live="polite" className="flex flex-col gap-3 rounded-xl border p-4">
            <p className="text-4xl font-semibold">
                {summary.total} <span className="ml-2 text-base font-normal text-muted-foreground">checked in</span>
            </p>
            <p className="text-sm text-muted-foreground">
                {summary.members} members · {summary.visitors} visitors · {summary.attendance_rate}% of {summary.active_members} active
                members
            </p>
            {usedMethods.length > 0 && (
                <ul className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    {usedMethods.map((method) => (
                        <li key={method.value}>
                            {method.label}: <strong>{summary.by_method[method.value]}</strong>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
