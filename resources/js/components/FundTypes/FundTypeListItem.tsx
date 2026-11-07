import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { FundType } from '@/types/giving';

interface FundTypeListItemProps {
    fund: FundType;
    onChange: (fund: FundType) => void;
    /** Asks to switch the fund off (the page confirms first) or, for one that is off, switches it on. */
    onToggleActive: (fund: FundType) => void;
}

/** One fund: its name and what it is for, whether it can be chosen for new gifts, and the buttons to change or switch it. */
export function FundTypeListItem({ fund, onChange, onToggleActive }: FundTypeListItemProps) {
    return (
        <li className="flex flex-wrap items-start justify-between gap-3 rounded-xl border p-3">
            <span className="min-w-0 flex-1">
                <span className="block font-medium">{fund.name}</span>
                {fund.description && <span className="block text-sm text-muted-foreground">{fund.description}</span>}
            </span>
            <span className="flex flex-wrap items-center gap-2">
                <StatusBadge label={fund.is_active ? 'In use' : 'Switched off'} tone={fund.is_active ? 'success' : 'neutral'} />
                <Button type="button" variant="outline" size="sm" onClick={() => onChange(fund)}>
                    Change
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => onToggleActive(fund)}>
                    {fund.is_active ? 'Switch off' : 'Switch on'}
                </Button>
            </span>
        </li>
    );
}
