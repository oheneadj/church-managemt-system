import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AttendanceSummary } from '@/types/members';

interface MemberAttendanceTabProps {
    summary: AttendanceSummary;
}

/**
 * How many services the member attended in the last 4, 8 and 12 weeks.
 */
export function MemberAttendanceTab({ summary }: MemberAttendanceTabProps) {
    const periods = [
        { label: 'Last 4 weeks', count: summary.last_4_weeks },
        { label: 'Last 8 weeks', count: summary.last_8_weeks },
        { label: 'Last 12 weeks', count: summary.last_12_weeks },
    ];

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {periods.map((period) => (
                <Card key={period.label} size="sm">
                    <CardHeader>
                        <CardTitle>{period.label}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-lg font-semibold">
                        {period.count} {period.count === 1 ? 'service' : 'services'}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
