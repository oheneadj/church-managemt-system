import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { Department } from '@/types/members';

interface DepartmentListItemProps {
    department: Department;
    onChange: (department: Department) => void;
    /** Asks to retire the department (the page confirms first) or, for one that is retired, brings it back. */
    onToggleActive: (department: Department) => void;
}

/** "1 member", "12 members". */
function membersText(count: number): string {
    return `${count} ${count === 1 ? 'member' : 'members'}`;
}

/** One department: its name, what it does, how many members (that you may see) it has, whether it is in use, and the buttons. */
export function DepartmentListItem({ department, onChange, onToggleActive }: DepartmentListItemProps) {
    return (
        <li className="flex flex-wrap items-start justify-between gap-3 rounded-xl border p-3">
            <span className="min-w-0 flex-1">
                <span className="block font-medium">{department.name}</span>
                {department.description && <span className="block text-sm text-muted-foreground">{department.description}</span>}
                {department.head && <span className="block text-sm text-muted-foreground">Head: {department.head.name}</span>}
                {department.members_count !== undefined && (
                    <span className="block text-sm text-muted-foreground">{membersText(department.members_count)}</span>
                )}
            </span>
            <span className="flex flex-wrap items-center gap-2">
                <StatusBadge label={department.is_active ? 'In use' : 'Retired'} tone={department.is_active ? 'success' : 'neutral'} />
                <Button type="button" variant="outline" size="sm" onClick={() => onChange(department)}>
                    Change
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => onToggleActive(department)}>
                    {department.is_active ? 'Retire' : 'Bring back'}
                </Button>
            </span>
        </li>
    );
}
