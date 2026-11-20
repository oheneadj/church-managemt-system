import { Users } from 'lucide-react';
import { EmptyState } from '@/components/ui/EmptyState';
import { formatClock } from '@/utils/formatClock';
import type { AttendanceRecord } from '@/types/services';

interface AttendanceListProps {
    records: AttendanceRecord[];
}

/**
 * The people checked in to a service, newest first, with how and when each arrived. Visitors are marked as such.
 */
export function AttendanceList({ records }: AttendanceListProps) {
    if (records.length === 0) {
        return <EmptyState icon={Users} title="Nobody has checked in yet" description="People appear here as they check in." />;
    }

    return (
        <ul className="flex flex-col divide-y rounded-xl border">
            {records.map((record) => (
                <li key={record.id} className="flex items-center justify-between gap-3 p-3 text-sm">
                    <span>
                        <span className="font-medium">{record.member?.full_name ?? record.visitor_name}</span>
                        <span className="block text-muted-foreground">{record.member ? record.member.member_number : 'Visitor'}</span>
                    </span>
                    <span className="text-right text-muted-foreground">
                        {record.method_label}
                        <span className="block">{formatClock(record.checked_in_at)}</span>
                    </span>
                </li>
            ))}
        </ul>
    );
}
