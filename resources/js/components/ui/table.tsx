import { ArrowDownIcon, ArrowUpIcon, ChevronsUpDownIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from 'cn';

/** How a column is sorted, in the words assistive technology uses. */
export type SortDirection = 'ascending' | 'descending' | 'none';

interface TableProps extends ComponentProps<'table'> {
    /** True while fresh data loads over rows already on screen: the rows stay, dimmed, and the table says it is busy. */
    refreshing?: boolean;
}

/**
 * The shared table (docs/design-system/interactions.md, section 14). It sits in a rounded, bordered surface that scrolls sideways
 * on a narrow screen instead of squeezing, and gives rows a hover and a selected look. Give it a `<TableCaption>` so it has a name.
 */
function Table({ className, refreshing = false, ...props }: TableProps) {
    return (
        <div data-slot="table-container" className="relative w-full overflow-x-auto rounded-xl border bg-card">
            <table
                data-slot="table"
                aria-busy={refreshing || undefined}
                className={cn('w-full caption-bottom text-left text-body transition-opacity', refreshing && 'opacity-60', className)}
                {...props}
            />
        </div>
    );
}

function TableCaption({ className, ...props }: ComponentProps<'caption'>) {
    return (
        <caption
            data-slot="table-caption"
            className={cn('border-t px-4 py-3 text-left text-body-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

function TableHeader({ className, ...props }: ComponentProps<'thead'>) {
    return <thead data-slot="table-header" className={cn('bg-muted [&_tr]:border-b', className)} {...props} />;
}

function TableBody({ className, ...props }: ComponentProps<'tbody'>) {
    return <tbody data-slot="table-body" className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

/** A row. `data-state="selected"` gives it the selected look (which is also announced through its checkbox, never by colour alone). */
function TableRow({ className, ...props }: ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn('border-b transition-colors hover:bg-surface-hover data-[state=selected]:bg-accent', className)}
            {...props}
        />
    );
}

interface CellProps {
    /** A number or an amount: right-aligned, with tabular figures so a column lines up. */
    numeric?: boolean;
}

function TableHead({ className, numeric = false, ...props }: ComponentProps<'th'> & CellProps) {
    return (
        <th
            data-slot="table-head"
            data-numeric={numeric || undefined}
            scope="col"
            className={cn(
                'h-11 px-4 align-middle text-caption font-medium whitespace-nowrap text-muted-foreground',
                numeric && 'text-right',
                className,
            )}
            {...props}
        />
    );
}

function TableCell({ className, numeric = false, ...props }: ComponentProps<'td'> & CellProps) {
    return (
        <td
            data-slot="table-cell"
            data-numeric={numeric || undefined}
            className={cn('px-4 py-3 align-middle', numeric && 'text-right whitespace-nowrap', className)}
            {...props}
        />
    );
}

/**
 * The action buttons at the end of a row (view, edit, delete). They are real buttons with names that say which row they belong to
 * ("Edit Alpha item"), always visible so they work on a touch screen and by keyboard, quiet until hovered, and kept together on the
 * right. Put icon buttons from the shared Button inside it.
 */
function TableRowActions({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="table-row-actions" role="group" className={cn('flex items-center justify-end gap-1', className)} {...props} />;
}

interface SortableHeadProps extends CellProps {
    /** How the table is sorted by this column right now. */
    sort: SortDirection;
    onSort: () => void;
    children: string;
}

/**
 * A column heading that sorts. The heading says how it is sorted (`aria-sort`), and the button inside it is what is pressed, by
 * mouse or keyboard. The arrow shows the direction with a shape as well as a position, so it does not depend on colour.
 */
function SortableHead({ sort, onSort, numeric = false, children }: SortableHeadProps) {
    const Icon = sort === 'ascending' ? ArrowUpIcon : sort === 'descending' ? ArrowDownIcon : ChevronsUpDownIcon;

    return (
        <TableHead numeric={numeric} aria-sort={sort}>
            <button
                type="button"
                onClick={onSort}
                className={cn(
                    '-mx-2 inline-flex h-8 items-center gap-1 rounded-md px-2 outline-none hover:bg-surface-hover hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring',
                    sort !== 'none' && 'text-foreground',
                )}
            >
                {children}
                <Icon aria-hidden="true" className="size-3.5" />
            </button>
        </TableHead>
    );
}

interface TableSkeletonRowsProps {
    rows: number;
    /** One entry for each column: true for a number column, so its placeholder sits on the right like the figures will. */
    columns: boolean[];
}

/**
 * Placeholder rows for the first load: the same number of rows and columns as the table will have, so nothing jumps when the data
 * arrives. The shapes are hidden from assistive technology and one status says the table is loading.
 */
function TableSkeletonRows({ rows, columns }: TableSkeletonRowsProps) {
    return (
        <>
            <TableRow className="hover:bg-transparent">
                <TableCell colSpan={columns.length} className="sr-only" role="status">
                    Loading
                </TableCell>
            </TableRow>
            {Array.from({ length: rows }, (_, row) => (
                <TableRow key={row} aria-hidden="true" className="hover:bg-transparent">
                    {columns.map((numeric, column) => (
                        <TableCell key={column} numeric={numeric}>
                            <Skeleton className={cn('h-4', numeric ? 'ml-auto w-12' : 'w-3/4')} />
                        </TableCell>
                    ))}
                </TableRow>
            ))}
        </>
    );
}

interface TableEmptyProps {
    columns: number;
    title: string;
    description?: string;
}

/** The one row a table shows when there is nothing to list: it says what is missing and what to do. */
function TableEmpty({ columns, title, description }: TableEmptyProps) {
    return (
        <TableRow className="hover:bg-transparent">
            <TableCell colSpan={columns} className="py-10 text-center">
                <p className="font-medium">{title}</p>
                {description && <p className="mt-1 text-muted-foreground">{description}</p>}
            </TableCell>
        </TableRow>
    );
}

export {
    SortableHead,
    Table,
    TableBody,
    TableRowActions,
    TableCaption,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
    TableSkeletonRows,
};
