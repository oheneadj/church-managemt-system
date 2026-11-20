import { Button } from '@/components/ui/button';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { StatusBadge, type StatusTone } from '@/components/ui/StatusBadge';
import type { PledgeStatus } from '@/constants/enums';
import type { Pledge } from '@/types/giving';
import { formatDate } from '@/utils/formatDate';

/** The colour that backs up each status word: paid is green, behind is amber, lapsed is red. */
const STATUS_TONES: Record<PledgeStatus, StatusTone> = {
    active: 'neutral',
    fulfilled: 'success',
    partial: 'warning',
    lapsed: 'danger',
};

interface PledgeListItemProps {
    pledge: Pledge;
    /** Shown only to staff who may change giving records. */
    onChange?: (pledge: Pledge) => void;
}

/**
 * One pledge: who made it and what it is for, how far it has been paid (a progress bar and the amounts the server
 * worked out), when it is due, where it stands, and a Change button for staff who may use it.
 */
export function PledgeListItem({ pledge, onChange }: PledgeListItemProps) {
    return (
        <li className="flex flex-col gap-2 rounded-xl border p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <span>
                    <span className="block font-medium">{pledge.description}</span>
                    <span className="block text-sm text-muted-foreground">
                        {pledge.member.full_name} · {pledge.fund_type.name} · due {formatDate(pledge.target_date)}
                    </span>
                </span>
                <span className="flex items-center gap-2">
                    <StatusBadge label={pledge.status_label} tone={STATUS_TONES[pledge.status]} />
                    {onChange && (
                        <Button type="button" variant="outline" size="sm" onClick={() => onChange(pledge)}>
                            Change
                        </Button>
                    )}
                </span>
            </div>
            <div
                role="progressbar"
                aria-label={`${pledge.description}: paid so far`}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={pledge.progress_percent}
                aria-valuetext={`${pledge.progress_percent} percent paid`}
                className="h-2 w-full overflow-hidden rounded-full bg-muted"
            >
                <div className="h-full bg-primary" style={{ width: `${pledge.progress_percent}%` }} />
            </div>
            <p className="text-sm text-muted-foreground">
                Paid <MoneyDisplay formatted={pledge.amount_paid_formatted} /> of{' '}
                <MoneyDisplay formatted={pledge.amount_pledged_formatted} /> ·{' '}
                <MoneyDisplay formatted={pledge.amount_outstanding_formatted} /> outstanding · {pledge.progress_percent}%
            </p>
        </li>
    );
}
