import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { GiftTable } from '@/components/Giving/GiftTable';
import { contribution } from '@/test/fixtures';

// The gifts as a table, for a wide screen (docs/design-system/interactions.md, section 14). This is financial display, so the rules
// that matter are tested: amounts are the server's own text, a voided gift is struck through and says why, and the Void button
// only exists for staff who may void, only on a gift that is not already voided, and names the gift it would void.

const gifts = [
    contribution(),
    contribution({
        id: 'gift-2',
        member: null,
        is_anonymous: true,
        amount: '50.00',
        amount_formatted: 'GHS 50.00',
        payment_method_label: 'MoMo',
        service: { id: 's1', name: 'Sunday Morning Service', service_date: '2026-11-01' },
        pledge: { id: 'p1', description: 'Building pledge' },
    }),
    contribution({
        id: 'gift-3',
        member: { id: 'member-9', full_name: 'Kofi Asare' },
        amount_formatted: 'GHS 75.00',
        is_voided: true,
        void_reason: 'Entered twice',
        voided_by: 'Ama Boateng',
    }),
];

function table(props: { onVoid?: (gift: (typeof gifts)[number]) => void; refreshing?: boolean } = {}) {
    return render(<GiftTable gifts={gifts} {...props} />);
}

const rowOf = (text: RegExp) => screen.getByRole('row', { name: text });

describe('GiftTable', () => {
    it('is a table named for what it holds, with a header for each column', () => {
        table();

        expect(screen.getByRole('table', { name: 'Gifts, newest first' })).toBeInTheDocument();
        expect(screen.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
            'Date',
            'Given by',
            'Fund',
            'How',
            'Amount',
            'Status',
        ]);
    });

    it('shows who gave, the fund, how, and the amount exactly as the server wrote it, right-aligned in tabular figures', () => {
        table();
        const row = rowOf(/Emmanuel Mensah/);

        expect(within(row).getByText('Tithe')).toBeInTheDocument();
        expect(within(row).getByText('Cash')).toBeInTheDocument();
        const amount = within(row).getByText('GHS 200.00');
        expect(amount.closest('td')).toHaveClass('text-right');
        expect(amount).toHaveClass('tabular-nums');
    });

    it('says Anonymous for a gift with no member, quietly, and shows the service and pledge it belongs to', () => {
        table();
        const row = rowOf(/Anonymous/);

        expect(within(row).getByText('Anonymous')).toHaveClass('text-muted-foreground');
        expect(within(row).getByText('MoMo')).toBeInTheDocument();
        expect(within(row).getByText('Sunday Morning Service')).toBeInTheDocument();
        expect(within(row).getByText('Pledge: Building pledge')).toBeInTheDocument();
    });

    it('strikes a voided gift through, says Voided, and writes who voided it and why, still showing the amount', () => {
        table();
        const row = rowOf(/Entered twice/);

        expect(within(row).getByText('GHS 75.00').closest('td')).toHaveClass('line-through', 'text-muted-foreground');
        expect(within(row).getByText('Voided')).toBeInTheDocument();
        expect(row).toHaveTextContent('Voided by Ama Boateng: Entered twice');
    });

    it('does not strike through or mark a gift that is not voided', () => {
        table();
        const row = rowOf(/Emmanuel Mensah/);

        expect(within(row).getByText('GHS 200.00').closest('td')).not.toHaveClass('line-through');
        expect(within(row).queryByText('Voided')).toBeNull();
    });

    it('has no Void column or button for staff who may not void', () => {
        table();

        expect(screen.queryByRole('columnheader', { name: 'Actions' })).toBeNull();
        expect(screen.queryByRole('button', { name: /^Void/ })).toBeNull();
    });

    it('gives staff who may void a Void button on each gift that is not voided, named for the gift, and none on a voided one', () => {
        table({ onVoid: vi.fn() });

        expect(screen.getByRole('columnheader', { name: 'Actions' })).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: /^Void the gift of/ })).toHaveLength(2);
        expect(screen.getByRole('button', { name: 'Void the gift of GHS 200.00 to Tithe' })).toBeInTheDocument();
        expect(within(rowOf(/Entered twice/)).queryByRole('button')).toBeNull();
    });

    it('hands the gift to be voided when its Void button is pressed, and voids nothing itself', async () => {
        const onVoid = vi.fn();
        table({ onVoid });

        await userEvent.setup().click(screen.getByRole('button', { name: 'Void the gift of GHS 50.00 to Tithe' }));

        expect(onVoid).toHaveBeenCalledOnce();
        expect(onVoid).toHaveBeenCalledWith(expect.objectContaining({ id: 'gift-2' }));
    });

    it('keeps its rows, dimmed and marked busy, while newer figures load', () => {
        table({ refreshing: true });

        expect(screen.getByRole('table')).toHaveAttribute('aria-busy', 'true');
        expect(screen.getByRole('table')).toHaveClass('opacity-60');
        expect(screen.getByText('GHS 200.00')).toBeInTheDocument();
    });
});
