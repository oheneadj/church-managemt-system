import { StatusBadge, type StatusTone } from '@/components/ui/StatusBadge';
import type { MemberStatus } from '@/constants/enums';

interface MemberStatusBadgeProps {
    status: MemberStatus;
    /** The label from the API (`status_label`). */
    label: string;
}

const TONES: Record<MemberStatus, StatusTone> = {
    active: 'success',
    prospect: 'warning',
    inactive: 'neutral',
    transferred: 'neutral',
    deceased: 'danger',
    archived: 'neutral',
};

/**
 * A member's status as a coloured label. Which colour each status gets is decided here, once.
 */
export function MemberStatusBadge({ status, label }: MemberStatusBadgeProps) {
    return <StatusBadge label={label} tone={TONES[status]} />;
}
