import { EyeIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
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
    TableRowActions,
    TableSkeletonRows,
} from '@/components/ui/table';
import { StatusBadge, type StatusTone } from '@/components/ui/StatusBadge';

interface DemoRow {
    id: string;
    name: string;
    status: string;
    tone: StatusTone;
    quantity: number;
    updated: string;
}

const ROWS: DemoRow[] = [
    { id: 'a', name: 'Alpha item', status: 'Active', tone: 'success', quantity: 1240, updated: '9 Nov 2026' },
    { id: 'b', name: 'Bravo item', status: 'Waiting', tone: 'warning', quantity: 86, updated: '8 Nov 2026' },
    { id: 'c', name: 'Charlie item', status: 'Active', tone: 'success', quantity: 12905, updated: '6 Nov 2026' },
    { id: 'd', name: 'Delta item', status: 'Paused', tone: 'neutral', quantity: 402, updated: '2 Nov 2026' },
    { id: 'e', name: 'Echo item', status: 'Failed', tone: 'danger', quantity: 7, updated: '30 Oct 2026' },
];

/** Which version of the table the demonstration shows. */
type View = 'data' | 'loading' | 'refreshing' | 'empty';

const VIEWS: Array<[View, string]> = [
    ['data', 'Data'],
    ['loading', 'First load'],
    ['refreshing', 'Refreshing'],
    ['empty', 'Nothing to show'],
];

type SortKey = 'name' | 'quantity';

/** The columns, true for a number column. */
const COLUMNS = [false, false, true, false, false, false];

const formatQuantity = (quantity: number) => new Intl.NumberFormat('en-GB').format(quantity);

/**
 * Tables: a sortable, selectable table with right-aligned figures, and the three other things a table can be: loading for the first
 * time, refreshing over the rows already shown, and empty.
 */
export function TablesSection() {
    const [view, setView] = useState<View>('data');
    const [sort, setSort] = useState<{ key: SortKey; direction: 'ascending' | 'descending' }>({ key: 'name', direction: 'ascending' });
    const [selected, setSelected] = useState<string[]>(['c']);
    const [items, setItems] = useState(ROWS);
    const [deleting, setDeleting] = useState<DemoRow | null>(null);

    const rows = useMemo(() => {
        const sorted = [...items].sort((a, b) => (sort.key === 'name' ? a.name.localeCompare(b.name) : a.quantity - b.quantity));

        return sort.direction === 'ascending' ? sorted : sorted.reverse();
    }, [items, sort]);

    const pressSort = (key: SortKey) =>
        setSort((now) => ({ key, direction: now.key === key && now.direction === 'ascending' ? 'descending' : 'ascending' }));
    const directionOf = (key: SortKey) => (sort.key === key ? sort.direction : 'none');
    const toggle = (id: string) => setSelected((now) => (now.includes(id) ? now.filter((other) => other !== id) : [...now, id]));
    const allSelected = items.length > 0 && selected.length === items.length;

    const confirmDelete = () => {
        if (deleting !== null) {
            setItems((now) => now.filter((row) => row.id !== deleting.id));
            setSelected((now) => now.filter((id) => id !== deleting.id));
            toast.success(`${deleting.name} deleted.`);
        }

        setDeleting(null);
    };

    return (
        <ShowcaseSection
            id="tables"
            title="Tables"
            description="Sort by pressing a heading, tick rows to select them, and switch the view to see loading, refreshing and empty. Figures are right-aligned and line up."
        >
            <div className="flex flex-wrap gap-2" role="group" aria-label="Table view">
                {VIEWS.map(([key, label]) => (
                    <Button
                        key={key}
                        size="sm"
                        variant={view === key ? 'default' : 'outline'}
                        aria-pressed={view === key}
                        onClick={() => setView(key)}
                    >
                        {label}
                    </Button>
                ))}
            </div>
            <p role="status" className="min-h-5 text-body-sm text-muted-foreground">
                {selected.length > 0 && view !== 'empty' ? `${selected.length} selected` : ''}
            </p>
            <Table refreshing={view === 'refreshing'}>
                <TableCaption>A demonstration table. Nothing here is real.</TableCaption>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="w-12">
                            <input
                                type="checkbox"
                                aria-label="Select all items"
                                className="size-5 accent-primary"
                                checked={allSelected}
                                ref={(box) => {
                                    if (box) box.indeterminate = selected.length > 0 && !allSelected;
                                }}
                                onChange={() => setSelected(allSelected ? [] : items.map((row) => row.id))}
                            />
                        </TableHead>
                        <SortableHead sort={directionOf('name')} onSort={() => pressSort('name')}>
                            Name
                        </SortableHead>
                        <SortableHead sort={directionOf('quantity')} onSort={() => pressSort('quantity')} numeric>
                            Quantity
                        </SortableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Updated</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {view === 'loading' && <TableSkeletonRows rows={4} columns={COLUMNS} />}
                    {view === 'empty' && (
                        <TableEmpty
                            columns={COLUMNS.length}
                            title="Nothing to show"
                            description="When there are items, they appear here."
                        />
                    )}
                    {(view === 'data' || view === 'refreshing') && rows.length === 0 && (
                        <TableEmpty
                            columns={COLUMNS.length}
                            title="Every row was deleted"
                            description="Restore the demonstration rows to see the table again."
                        />
                    )}
                    {(view === 'data' || view === 'refreshing') &&
                        rows.map((row) => (
                            <TableRow key={row.id} data-state={selected.includes(row.id) ? 'selected' : undefined}>
                                <TableCell>
                                    <input
                                        type="checkbox"
                                        aria-label={`Select ${row.name}`}
                                        className="size-5 accent-primary"
                                        checked={selected.includes(row.id)}
                                        onChange={() => toggle(row.id)}
                                    />
                                </TableCell>
                                <TableCell className="font-medium">{row.name}</TableCell>
                                <TableCell numeric>{formatQuantity(row.quantity)}</TableCell>
                                <TableCell>
                                    <StatusBadge label={row.status} tone={row.tone} />
                                </TableCell>
                                <TableCell className="text-muted-foreground">{row.updated}</TableCell>
                                <TableCell>
                                    <TableRowActions>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`View ${row.name}`}
                                            onClick={() => toast.info(`Viewing ${row.name}`)}
                                        >
                                            <EyeIcon />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Edit ${row.name}`}
                                            onClick={() => toast.info(`Editing ${row.name}`)}
                                        >
                                            <PencilIcon />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Delete ${row.name}`}
                                            onClick={() => setDeleting(row)}
                                        >
                                            <Trash2Icon />
                                        </Button>
                                    </TableRowActions>
                                </TableCell>
                            </TableRow>
                        ))}
                </TableBody>
            </Table>
            <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" disabled={items.length === ROWS.length} onClick={() => setItems(ROWS)}>
                    Restore the demonstration rows
                </Button>
            </div>
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.name ?? 'this item'}?`}
                description="This removes the item and cannot be undone."
                confirmLabel="Delete item"
                destructive
                onConfirm={confirmDelete}
            />
        </ShowcaseSection>
    );
}
