import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { ServiceSchedule } from '@/types/services';

interface ServiceScheduleListItemProps {
    schedule: ServiceSchedule;
    onEdit: (schedule: ServiceSchedule) => void;
    onToggle: (schedule: ServiceSchedule) => void;
    /** True while a change to this schedule is being saved. */
    busy?: boolean;
}

/**
 * One weekly schedule: what it makes, on which day and at what time, whether it is on, and buttons to change it or
 * turn it off (or on again).
 */
export function ServiceScheduleListItem({ schedule, onEdit, onToggle, busy = false }: ServiceScheduleListItemProps) {
    const times = schedule.starts_at ? ` · ${schedule.starts_at}${schedule.ends_at ? ` to ${schedule.ends_at}` : ''}` : '';

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
            <span>
                <span className="block font-medium">{schedule.name}</span>
                <span className="block text-sm text-muted-foreground">
                    Every {schedule.weekday_label}
                    {times} · {schedule.type_label}
                </span>
            </span>
            <span className="flex items-center gap-2">
                <StatusBadge label={schedule.is_active ? 'On' : 'Off'} tone={schedule.is_active ? 'success' : 'neutral'} />
                <Button type="button" variant="outline" size="sm" onClick={() => onEdit(schedule)}>
                    Change
                </Button>
                <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => onToggle(schedule)}>
                    {schedule.is_active ? 'Turn off' : 'Turn on'}
                </Button>
            </span>
        </li>
    );
}
