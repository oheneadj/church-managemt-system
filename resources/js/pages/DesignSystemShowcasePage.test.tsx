import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { LOADING_INDICATOR_DELAY_MS } from '@/constants/interaction';
import { PASSWORD_MIN_LENGTH } from '@/constants/password';
import { DesignSystemShowcasePage } from '@/pages/DesignSystemShowcasePage';

// The showcase is where the design system is looked at and checked (docs/design-system/CONTRACT.md, section 9). These tests drive the
// demonstrations the way a person would, so a broken demonstration is caught, and check the page stays free of the product's own subject.

function setup() {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    render(<DesignSystemShowcasePage />);

    return user;
}

describe('DesignSystemShowcasePage', () => {
    beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
    afterEach(() => vi.useRealTimers());

    it('has one page heading and a heading for each family of components', () => {
        setup();

        expect(screen.getByRole('heading', { level: 1, name: 'Design system showcase' })).toBeInTheDocument();
        for (const name of ['Buttons', 'Fields', 'Feedback', 'Loading', 'Tables', 'Dialogs and tabs', 'Motion']) {
            expect(screen.getByRole('heading', { level: 2, name })).toBeInTheDocument();
        }
    });

    it('says nothing about the business: it shows components, not workflows', () => {
        setup();

        expect(document.body.textContent).not.toMatch(/church|member|giving|pledge|contribution|attendance|sms credit/i);
    });

    it('shows no spinner for quick work and a spinner for slow work, and ignores a second press', async () => {
        const user = setup();
        const quick = screen.getByRole('button', { name: /Save changes \(quick\)/ });
        const slow = screen.getByRole('button', { name: /Save changes \(slow\)/ });

        await user.click(quick);
        expect(quick).toHaveAttribute('aria-busy', 'true');
        await act(() => vi.advanceTimersByTimeAsync(250));
        expect(screen.getAllByText(/Changes saved\./)).toHaveLength(1);
        expect(screen.queryByText('Saving…')).not.toBeInTheDocument();

        await user.click(slow);
        await user.click(slow);
        await act(() => vi.advanceTimersByTimeAsync(LOADING_INDICATOR_DELAY_MS));
        expect(slow).toHaveTextContent('Saving…');
        await act(() => vi.advanceTimersByTimeAsync(2500));
        expect(screen.getAllByText(/Changes saved\./)).toHaveLength(2);
    });

    it('ticks the password length requirement as the person types', async () => {
        const user = setup();
        const field = screen.getByLabelText('Choose a password');

        await user.type(field, 'a'.repeat(PASSWORD_MIN_LENGTH - 1));
        expect(screen.getByRole('list', { name: 'Password requirements' })).toHaveTextContent('not met yet');

        await user.type(field, 'a');
        expect(screen.getByRole('list', { name: 'Password requirements' })).toHaveTextContent(': met');
    });

    it('shows the invalid field with its error tied to it', () => {
        setup();

        expect(screen.getByLabelText('With an error')).toHaveAccessibleDescription('Enter an email address like name@example.com.');
        expect(screen.getByLabelText('Disabled')).toBeDisabled();
        expect(screen.getByLabelText('Read-only')).toHaveAttribute('readonly');
    });

    it('opens the form dialog, shows loading on its own button, closes when done and returns focus to the opener', async () => {
        const user = setup();
        const opener = screen.getByRole('button', { name: 'Open form dialog' });

        await user.click(opener);
        const dialog = await screen.findByRole('dialog', { name: 'Edit name' });
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));
        expect(within(dialog).getByRole('button', { name: 'Save changes' })).toHaveAttribute('aria-busy', 'true');
        await act(() => vi.advanceTimersByTimeAsync(1500));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        await waitFor(() => expect(opener).toHaveFocus());
    });

    it('closes the form dialog with Escape', async () => {
        const user = setup();

        await user.click(screen.getByRole('button', { name: 'Open form dialog' }));
        await screen.findByRole('dialog', { name: 'Edit name' });
        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('asks before a destructive action, names it, and can be cancelled', async () => {
        const user = setup();

        await user.click(screen.getAllByRole('button', { name: 'Delete item' })[1]!);
        const dialog = await screen.findByRole('alertdialog', { name: 'Delete this item?' });
        expect(within(dialog).getByRole('button', { name: 'Delete item' })).toBeInTheDocument();

        await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));
        expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    });

    it('fires each kind of toast', async () => {
        const user = setup();
        const success = vi.spyOn(toast, 'success').mockReturnValue(1);
        const info = vi.spyOn(toast, 'info').mockReturnValue(1);
        const warning = vi.spyOn(toast, 'warning').mockReturnValue(1);
        const error = vi.spyOn(toast, 'error').mockReturnValue(1);
        const promise = vi.spyOn(toast, 'promise').mockReturnValue(undefined as never);

        await user.click(screen.getByRole('button', { name: 'Success toast' }));
        await user.click(screen.getByRole('button', { name: 'Information toast' }));
        await user.click(screen.getByRole('button', { name: 'Warning toast' }));
        await user.click(screen.getByRole('button', { name: 'Error toast' }));
        await user.click(screen.getByRole('button', { name: 'Progress toast' }));

        expect(success).toHaveBeenCalledWith('Changes saved.');
        expect(info).toHaveBeenCalledWith('Your report is being prepared.');
        expect(warning).toHaveBeenCalledWith('You have unsaved changes.');
        expect(error).toHaveBeenCalledWith(
            'We could not save your changes. Try again.',
            expect.objectContaining({ duration: Infinity, closeButton: true }),
        );
        expect(promise).toHaveBeenCalledOnce();
    });

    it('shows a skeleton announced once, an error with retry, and an empty state with a next step', () => {
        setup();

        expect(screen.getByRole('status', { name: 'Loading content' })).toBeInTheDocument();
        expect(screen.getByText(/We could not load this\./).closest('[role="alert"]')).not.toBeNull();
        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Nothing here yet' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add item' })).toBeInTheDocument();
    });

    it('moves between tabs with the arrow keys and leaves a disabled tab alone', async () => {
        const user = setup();

        screen.getByRole('tab', { name: 'First' }).focus();
        await user.keyboard('{ArrowRight}');

        expect(screen.getByRole('tab', { name: 'Second' })).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByRole('tab', { name: 'Disabled' })).toBeDisabled();
    });

    it('sorts the table when a heading is pressed, and reverses on a second press', async () => {
        const user = setup();
        const names = () =>
            within(screen.getByRole('table', { name: /demonstration table/i }))
                .getAllByRole('row')
                .slice(1)
                .map((row) => within(row).getAllByRole('cell')[1]?.textContent);

        expect(names()).toEqual(['Alpha item', 'Bravo item', 'Charlie item', 'Delta item', 'Echo item']);
        await user.click(screen.getByRole('button', { name: 'Name' }));
        expect(screen.getByRole('columnheader', { name: 'Name' })).toHaveAttribute('aria-sort', 'descending');
        expect(names()[0]).toBe('Echo item');
        await user.click(screen.getByRole('button', { name: 'Quantity' }));
        expect(screen.getByRole('columnheader', { name: 'Quantity' })).toHaveAttribute('aria-sort', 'ascending');
        expect(screen.getByRole('columnheader', { name: 'Name' })).toHaveAttribute('aria-sort', 'none');
        expect(names()[0]).toBe('Echo item');
    });

    it('selects rows, counts them, and ticks select-all only when every row is selected', async () => {
        const user = setup();
        const all = screen.getByRole('checkbox', { name: 'Select all items' });

        expect(screen.getByText('1 selected')).toBeInTheDocument();
        expect(all).not.toBeChecked();

        await user.click(all);
        expect(screen.getByText('5 selected')).toBeInTheDocument();
        expect(all).toBeChecked();
        expect(screen.getByRole('row', { name: /Alpha item/ })).toHaveAttribute('data-state', 'selected');

        await user.click(screen.getByRole('checkbox', { name: 'Select Alpha item' }));
        expect(screen.getByText('4 selected')).toBeInTheDocument();
        expect(all).not.toBeChecked();
        expect(screen.getByRole('row', { name: /Alpha item/ })).not.toHaveAttribute('data-state');
    });

    it('switches the table between data, first load, refreshing and empty', async () => {
        const user = setup();
        const table = () => screen.getByRole('table', { name: /demonstration table/i });

        await user.click(screen.getByRole('button', { name: 'First load' }));
        expect(within(table()).getByRole('status')).toHaveTextContent('Loading');
        expect(within(table()).queryByText('Alpha item')).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Refreshing' }));
        expect(table()).toHaveAttribute('aria-busy', 'true');
        expect(within(table()).getByText('Alpha item')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Nothing to show' }));
        expect(within(table()).getByText('When there are items, they appear here.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Nothing to show' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('gives every row view, edit and delete buttons named for that row', async () => {
        const user = setup();
        const info = vi.spyOn(toast, 'info').mockReturnValue(1);

        for (const verb of ['View', 'Edit', 'Delete']) {
            expect(screen.getByRole('button', { name: `${verb} Bravo item` })).toBeInTheDocument();
        }
        expect(screen.getAllByRole('button', { name: /^Edit / })).toHaveLength(5);

        await user.click(screen.getByRole('button', { name: 'Edit Bravo item' }));
        expect(info).toHaveBeenCalledWith('Editing Bravo item');
    });

    it('asks before deleting a row, names the row, deletes only when confirmed, and can restore the demonstration rows', async () => {
        const user = setup();
        const success = vi.spyOn(toast, 'success').mockReturnValue(1);

        await user.click(screen.getByRole('button', { name: 'Delete Delta item' }));
        const dialog = await screen.findByRole('alertdialog', { name: 'Delete Delta item?' });
        expect(within(dialog).getByRole('button', { name: 'Delete item' })).toHaveAttribute('data-variant', 'danger');

        await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));
        expect(screen.getByText('Delta item')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Delete Delta item' }));
        await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete item' }));

        expect(screen.queryByText('Delta item')).not.toBeInTheDocument();
        expect(success).toHaveBeenCalledWith('Delta item deleted.');

        await user.click(screen.getByRole('button', { name: 'Restore the demonstration rows' }));
        expect(screen.getByText('Delta item')).toBeInTheDocument();
    });

    it('says so when every row has been deleted', async () => {
        const user = setup();
        vi.spyOn(toast, 'success').mockReturnValue(1);

        for (const name of ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo']) {
            await user.click(screen.getByRole('button', { name: `Delete ${name} item` }));
            await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete item' }));
            await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
        }

        expect(screen.getByText('Every row was deleted')).toBeInTheDocument();
    });

    it('shows each motion token as a bar driven by that token', () => {
        setup();

        const bars = document.querySelectorAll<HTMLElement>('section[aria-labelledby="motion-title"] [style*="--bar-duration"]');

        expect(bars).toHaveLength(5);
        expect(Array.from(bars, (bar) => bar.style.getPropertyValue('--bar-duration'))).toEqual([
            'var(--motion-duration-instant)',
            'var(--motion-duration-micro)',
            'var(--motion-duration-component)',
            'var(--motion-duration-overlay)',
            'var(--motion-duration-content)',
        ]);
    });
});
