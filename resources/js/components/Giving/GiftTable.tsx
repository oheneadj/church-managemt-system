import { Button } from '@/components/ui/button';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow, TableRowActions } from '@/components/ui/table';
import type { Contribution } from '@/types/giving';
import { formatDate } from '@/utils/formatDate';
import { cn } from 'cn';

interface GiftTableProps {
    gifts: readonly Contribution[];
    /** Given only to staff who may void gifts: the Void button is then drawn on each gift that is not already voided. */
    onVoid?: (gift: Contribution) => void;
    /** True while newer figures load: the rows stay, dimmed, so nothing jumps. */
    refreshing?: boolean;
}

/** The small grey line under a gift's method: the service it was given at and the pledge it pays, if any. */
function detailsOf(gift: Contribution): string[] {
    return [gift.service ? gift.service.name : null, gift.pledge ? `Pledge: ${gift.pledge.description}` : null].filter(
        (part): part is string => part !== null,
    );
}

/**
 * The church's gifts as a table, for a wide screen (the shared table, docs/design-system/interactions.md, section 14). Amounts are the
 * texts the server formatted, right-aligned in tabular figures, and never worked out here. A voided gift stays in the list when asked
 * for: its amount is struck through, it says Voided, and the reason is written under who gave. The Void button names the gift it
 * would void, because a row's action must say which row it belongs to.
 */
export function GiftTable({ gifts, onVoid, refreshing = false }: GiftTableProps) {
    return (
        <Table refreshing={refreshing}>
            <TableCaption className="sr-only">Gifts, newest first</TableCaption>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>Date</TableHead>
                    <TableHead>Given by</TableHead>
                    <TableHead>Fund</TableHead>
                    <TableHead>How</TableHead>
                    <TableHead numeric>Amount</TableHead>
                    <TableHead>Status</TableHead>
                    {onVoid && <TableHead className="text-right">Actions</TableHead>}
                </TableRow>
            </TableHeader>
            <TableBody>
                {gifts.map((gift) => (
                    <TableRow key={gift.id}>
                        <TableCell className="whitespace-nowrap">{formatDate(gift.contributed_at)}</TableCell>
                        <TableCell>
                            <span className={cn('block', gift.member === null && 'text-muted-foreground')}>
                                {gift.member?.full_name ?? 'Anonymous'}
                            </span>
                            {gift.is_voided && (
                                <span className="block text-body-sm text-muted-foreground">
                                    Voided{gift.voided_by ? ` by ${gift.voided_by}` : ''}: {gift.void_reason}
                                </span>
                            )}
                        </TableCell>
                        <TableCell>{gift.fund_type.name}</TableCell>
                        <TableCell>
                            <span className="block">{gift.payment_method_label}</span>
                            {detailsOf(gift).map((detail) => (
                                <span key={detail} className="block text-body-sm text-muted-foreground">
                                    {detail}
                                </span>
                            ))}
                        </TableCell>
                        <TableCell numeric className={cn('font-medium', gift.is_voided && 'text-muted-foreground line-through')}>
                            <MoneyDisplay formatted={gift.amount_formatted} />
                        </TableCell>
                        <TableCell>{gift.is_voided && <StatusBadge label="Voided" tone="danger" />}</TableCell>
                        {onVoid && (
                            <TableCell>
                                <TableRowActions>
                                    {!gift.is_voided && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            aria-label={`Void the gift of ${gift.amount_formatted} to ${gift.fund_type.name}`}
                                            onClick={() => onVoid(gift)}
                                        >
                                            Void
                                        </Button>
                                    )}
                                </TableRowActions>
                            </TableCell>
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
