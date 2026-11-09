import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import {
    SortableHead,
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
    TableSkeletonRows,
} from '@/components/ui/table';

// The shared table (docs/design-system/interactions.md, section 14): named, with real headers, sortable by a button that says how it
// is sorted, figures that line up, a selected look, and honest loading, refreshing and empty states.

function sample(props: { sort?: 'ascending' | 'descending' | 'none'; onSort?: () => void } = {}) {
    return render(
        <Table>
            <TableCaption>Items</TableCaption>
            <TableHeader>
                <TableRow>
                    <SortableHead sort={props.sort ?? 'none'} onSort={props.onSort ?? vi.fn()}>
                        Name
                    </SortableHead>
                    <TableHead numeric>Quantity</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow data-state="selected">
                    <TableCell>Alpha</TableCell>
                    <TableCell numeric>1,240</TableCell>
                </TableRow>
                <TableRow>
                    <TableCell>Bravo</TableCell>
                    <TableCell numeric>86</TableCell>
                </TableRow>
            </TableBody>
        </Table>,
    );
}

describe('Table', () => {
    it('is a named table with real column headers', () => {
        sample();

        expect(screen.getByRole('table', { name: 'Items' })).toBeInTheDocument();
        expect(screen.getAllByRole('columnheader')).toHaveLength(2);
        expect(screen.getByRole('columnheader', { name: 'Quantity' })).toHaveAttribute('scope', 'col');
    });

    it('right-aligns numbers, marks them for tabular figures, and leaves text on the left', () => {
        sample();

        expect(screen.getByRole('cell', { name: '1,240' })).toHaveClass('text-right');
        expect(screen.getByRole('cell', { name: '1,240' })).toHaveAttribute('data-numeric', 'true');
        expect(screen.getByRole('columnheader', { name: 'Quantity' })).toHaveClass('text-right');
        expect(screen.getByRole('cell', { name: 'Alpha' })).not.toHaveAttribute('data-numeric');
    });

    it('gives a selected row its own state', () => {
        sample();

        expect(screen.getByRole('row', { name: /Alpha/ })).toHaveAttribute('data-state', 'selected');
        expect(screen.getByRole('row', { name: /Bravo/ })).not.toHaveAttribute('data-state');
    });

    it('says it is busy and dims its rows while refreshing, keeping them on screen', () => {
        render(
            <Table refreshing>
                <TableBody>
                    <TableRow>
                        <TableCell>Alpha</TableCell>
                    </TableRow>
                </TableBody>
            </Table>,
        );

        expect(screen.getByRole('table')).toHaveAttribute('aria-busy', 'true');
        expect(screen.getByRole('table')).toHaveClass('opacity-60');
        expect(screen.getByText('Alpha')).toBeInTheDocument();
    });

    it('is not marked busy at rest', () => {
        sample();

        expect(screen.getByRole('table')).not.toHaveAttribute('aria-busy');
    });
});

describe('SortableHead', () => {
    it.each(['ascending', 'descending', 'none'] as const)('tells assistive technology it is sorted %s', (sort) => {
        sample({ sort });

        expect(screen.getByRole('columnheader', { name: 'Name' })).toHaveAttribute('aria-sort', sort);
    });

    it('sorts when its button is pressed, with the mouse or the keyboard', async () => {
        const onSort = vi.fn();
        const user = userEvent.setup();
        sample({ onSort });
        const button = within(screen.getByRole('columnheader', { name: 'Name' })).getByRole('button', { name: 'Name' });

        await user.click(button);
        button.focus();
        await user.keyboard('{Enter}');
        await user.keyboard(' ');

        expect(onSort).toHaveBeenCalledTimes(3);
    });

    it('shows its direction with an icon that assistive technology skips', () => {
        sample({ sort: 'ascending' });

        expect(
            within(screen.getByRole('columnheader', { name: 'Name' }))
                .getByRole('button')
                .querySelector('svg'),
        ).toHaveAttribute('aria-hidden', 'true');
    });
});

describe('TableSkeletonRows', () => {
    it('says once that it is loading, and hides the placeholder shapes', () => {
        render(
            <Table>
                <TableBody>
                    <TableSkeletonRows rows={3} columns={[false, true]} />
                </TableBody>
            </Table>,
        );

        expect(screen.getByRole('status')).toHaveTextContent('Loading');
        expect(document.querySelectorAll('tr[aria-hidden="true"]')).toHaveLength(3);
        expect(document.querySelectorAll('tr[aria-hidden="true"] td')).toHaveLength(6);
    });

    it('puts the placeholder of a number column on the right, like the figures will be', () => {
        render(
            <Table>
                <TableBody>
                    <TableSkeletonRows rows={1} columns={[false, true]} />
                </TableBody>
            </Table>,
        );

        const cells = document.querySelectorAll('tr[aria-hidden="true"] td');

        expect(cells[0]).not.toHaveClass('text-right');
        expect(cells[1]).toHaveClass('text-right');
    });
});

describe('TableEmpty', () => {
    it('says what is missing and what to do, across every column', () => {
        render(
            <Table>
                <TableBody>
                    <TableEmpty columns={4} title="Nothing to show" description="When there are items, they appear here." />
                </TableBody>
            </Table>,
        );

        expect(screen.getByText('Nothing to show')).toBeInTheDocument();
        expect(screen.getByText('When there are items, they appear here.')).toBeInTheDocument();
        expect(screen.getByRole('cell')).toHaveAttribute('colspan', '4');
    });

    it('shows only the title when there is no description', () => {
        render(
            <Table>
                <TableBody>
                    <TableEmpty columns={1} title="No results" />
                </TableBody>
            </Table>,
        );

        expect(screen.getByText('No results')).toBeInTheDocument();
        expect(document.querySelectorAll('td p')).toHaveLength(1);
    });
});
