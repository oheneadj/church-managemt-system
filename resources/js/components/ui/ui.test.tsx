import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Users } from 'lucide-react';
import { describe, expect, it, vi } from 'vitest';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { StatusBadge } from '@/components/ui/StatusBadge';

describe('PageHeader', () => {
    it('shows one heading, the description and the actions', () => {
        render(<PageHeader title="Members" description="42 found" actions={<button>Add member</button>} />);

        expect(screen.getByRole('heading', { level: 1, name: 'Members' })).toBeInTheDocument();
        expect(screen.getByText('42 found')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add member' })).toBeInTheDocument();
    });
});

describe('StatusBadge', () => {
    it('always shows the status as words', () => {
        render(<StatusBadge label="Inactive" tone="neutral" />);

        expect(screen.getByText('Inactive')).toBeInTheDocument();
    });
});

describe('EmptyState', () => {
    it('says what is missing and offers the next step', () => {
        render(<EmptyState icon={Users} title="No members found" description="Try another search." action={<button>Add member</button>} />);

        expect(screen.getByRole('heading', { name: 'No members found' })).toBeInTheDocument();
        expect(screen.getByText('Try another search.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add member' })).toBeInTheDocument();
    });
});

describe('MoneyDisplay', () => {
    it('shows the amount exactly as the API formatted it', () => {
        render(<MoneyDisplay formatted="GHS 2,400.00" />);

        expect(screen.getByText('GHS 2,400.00')).toBeInTheDocument();
    });
});

describe('ErrorNotice', () => {
    it('announces the message and retries on request', async () => {
        const onRetry = vi.fn();
        render(<ErrorNotice message="Cannot reach the server." onRetry={onRetry} />);

        expect(screen.getByRole('alert')).toHaveTextContent('Cannot reach the server.');
        await userEvent.click(screen.getByRole('button', { name: 'Try again' }));
        expect(onRetry).toHaveBeenCalledOnce();
    });

    it('has no retry button unless a retry is given', () => {
        render(<ErrorNotice message="Something went wrong." />);

        expect(screen.queryByRole('button', { name: 'Try again' })).not.toBeInTheDocument();
    });
});

describe('Pagination', () => {
    it('moves between pages', async () => {
        const onPageChange = vi.fn();
        render(<Pagination page={2} lastPage={4} onPageChange={onPageChange} />);

        expect(screen.getByText('Page 2 of 4')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Next' }));
        await userEvent.click(screen.getByRole('button', { name: 'Previous' }));
        expect(onPageChange).toHaveBeenNthCalledWith(1, 3);
        expect(onPageChange).toHaveBeenNthCalledWith(2, 1);
    });

    it('stops at the first and last page', () => {
        const { rerender } = render(<Pagination page={1} lastPage={3} onPageChange={vi.fn()} />);
        expect(screen.getByRole('button', { name: 'Previous' })).toBeDisabled();

        rerender(<Pagination page={3} lastPage={3} onPageChange={vi.fn()} />);
        expect(screen.getByRole('button', { name: 'Next' })).toBeDisabled();
    });

    it('waits while the next page is loading', () => {
        render(<Pagination page={1} lastPage={3} onPageChange={vi.fn()} disabled />);

        expect(screen.getByRole('button', { name: 'Next' })).toBeDisabled();
    });

    it('is hidden when everything fits on one page', () => {
        render(<Pagination page={1} lastPage={1} onPageChange={vi.fn()} />);

        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    });
});

describe('ConfirmDialog', () => {
    it('asks first, and only acts when confirmed', async () => {
        const onConfirm = vi.fn();
        render(
            <ConfirmDialog
                open
                onOpenChange={vi.fn()}
                title="Deactivate member?"
                description="They will leave the directory."
                confirmLabel="Deactivate"
                onConfirm={onConfirm}
            />,
        );

        expect(screen.getByRole('alertdialog', { name: 'Deactivate member?' })).toBeInTheDocument();
        expect(onConfirm).not.toHaveBeenCalled();
        await userEvent.click(screen.getByRole('button', { name: 'Deactivate' }));
        expect(onConfirm).toHaveBeenCalledOnce();
    });

    it('cannot be confirmed twice while the action runs', () => {
        render(<ConfirmDialog open onOpenChange={vi.fn()} title="Sure?" description="…" confirmLabel="Yes" onConfirm={vi.fn()} pending />);

        expect(screen.getByRole('button', { name: 'Yes' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    });
});
