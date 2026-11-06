import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { STAFF_PERMISSIONS, STAFF_ROLES } from '@/constants/enums';
import type { StaffMember } from '@/types/staff';
import { formatDateTime } from '@/utils/formatDateTime';

interface StaffListItemProps {
    staff: StaffMember;
    onChange: (staff: StaffMember) => void;
    /** Asks to deactivate (the page confirms first) or, for someone switched off, reactivates. */
    onToggleActive: (staff: StaffMember) => void;
}

/** The words for a role or permission value, or the value itself if the app does not know it yet. */
function labelOf(options: readonly { value: string; label: string }[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

/**
 * One staff member: who they are, their role, whether they can sign in, when they last did, any permissions given on top of
 * their role, and the buttons to change them or switch them off or on.
 */
export function StaffListItem({ staff, onChange, onToggleActive }: StaffListItemProps) {
    const extras = staff.extra_permissions.map((permission) => labelOf(STAFF_PERMISSIONS, permission));

    return (
        <li className="flex flex-col gap-2 rounded-xl border p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <span>
                    <span className="block font-medium">{staff.name}</span>
                    <span className="block text-sm text-muted-foreground">{staff.email}</span>
                    <span className="block text-sm text-muted-foreground">{staff.phone}</span>
                </span>
                <span className="flex flex-wrap items-center gap-2">
                    {staff.roles.map((role) => (
                        <StatusBadge key={role} label={labelOf(STAFF_ROLES, role)} tone="neutral" />
                    ))}
                    <StatusBadge label={staff.is_active ? 'Active' : 'Deactivated'} tone={staff.is_active ? 'success' : 'danger'} />
                </span>
            </div>
            <p className="text-sm text-muted-foreground">
                {staff.last_login_at === null ? 'Never signed in' : `Last signed in ${formatDateTime(staff.last_login_at)}`}
            </p>
            {extras.length > 0 && <p className="text-sm text-muted-foreground">Also allowed: {extras.join(', ')}</p>}
            <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" onClick={() => onChange(staff)}>
                    Change
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => onToggleActive(staff)}>
                    {staff.is_active ? 'Deactivate' : 'Reactivate'}
                </Button>
            </div>
        </li>
    );
}
