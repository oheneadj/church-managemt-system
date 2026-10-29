import { Button } from '@/components/ui/button';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { Contribution } from '@/types/giving';
import { formatDate } from '@/utils/formatDate';

interface ContributionListItemProps {
    gift: Contribution;
    /** Shown only to staff who may void gifts. */
    onVoid?: (gift: Contribution) => void;
}

/**
 * One gift: who gave, to which fund, how much, how and when, and a Void button. A voided gift stays in the list when
 * asked for, struck through, with the reason it was voided.
 */
export function ContributionListItem({ gift, onVoid }: ContributionListItemProps) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
            <span>
                <span className={`block font-medium ${gift.is_voided ? 'line-through' : ''}`}>
                    <MoneyDisplay formatted={gift.amount_formatted} /> · {gift.fund_type.name}
                </span>
                <span className="block text-sm text-muted-foreground">
                    {gift.member?.full_name ?? 'Anonymous'} · {formatDate(gift.contributed_at)} · {gift.payment_method_label}
                    {gift.service ? ` · ${gift.service.name}` : ''}
                    {gift.pledge ? ` · Pledge: ${gift.pledge.description}` : ''}
                </span>
                {gift.is_voided && (
                    <span className="block text-sm text-muted-foreground">
                        Voided{gift.voided_by ? ` by ${gift.voided_by}` : ''}: {gift.void_reason}
                    </span>
                )}
            </span>
            <span className="flex items-center gap-2">
                {gift.is_voided && <StatusBadge label="Voided" tone="danger" />}
                {!gift.is_voided && onVoid && (
                    <Button type="button" variant="outline" size="sm" onClick={() => onVoid(gift)}>
                        Void
                    </Button>
                )}
            </span>
        </li>
    );
}
