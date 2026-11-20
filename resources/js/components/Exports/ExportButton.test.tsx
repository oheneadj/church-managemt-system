import { QueryClient, QueryClientProvider, useMutation } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ExportButton } from '@/components/Exports/ExportButton';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import type { ExportQueued } from '@/types/exports';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

/** The button with a real mutation around the function a test supplies. */
function Harness({ ask }: { ask: () => Promise<ExportQueued> }) {
    const request = useMutation({ mutationFn: ask });

    return <ExportButton label="Export list" contents="the things, as a spreadsheet (CSV)" request={request} />;
}

function show(ask: () => Promise<ExportQueued>) {
    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
            <Harness ask={ask} />
        </QueryClientProvider>,
    );
}

describe('ExportButton', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        useAuthStore.setState({
            user: { id: 'u1', name: 'Akosua', phone: '+233244123456', email: 'akosua@example.com', roles: [], permissions: [] },
        });
    });

    it('asks first, saying what is asked for, where the link goes, how long it works and when the file goes', async () => {
        const ask = vi.fn();
        const user = userEvent.setup();
        show(ask);

        await user.click(screen.getByRole('button', { name: 'Export list' }));

        const question = await screen.findByRole('alertdialog');
        expect(question).toHaveTextContent('This asks for the things, as a spreadsheet (CSV).');
        expect(question).toHaveTextContent('email the download link to akosua@example.com');
        expect(question).toHaveTextContent('works for 1 hour');
        expect(question).toHaveTextContent('deleted after 7 days');
        expect(ask).not.toHaveBeenCalled();
    });

    it('says "you" when the account has no email address on record', async () => {
        useAuthStore.setState({ user: { id: 'u1', name: 'Akosua', phone: '+233244123456', email: null, roles: [], permissions: [] } });
        const user = userEvent.setup();
        show(vi.fn());

        await user.click(screen.getByRole('button', { name: 'Export list' }));

        expect(await screen.findByRole('alertdialog')).toHaveTextContent('email the download link to you.');
    });

    it("asks once when confirmed, shows the server's words and closes", async () => {
        const ask = vi
            .fn()
            .mockResolvedValue({ export_id: 'e1', message: 'Export queued. You will receive a download link by email when it is ready.' });
        const user = userEvent.setup();
        show(ask);

        await user.click(screen.getByRole('button', { name: 'Export list' }));
        await user.click(await screen.findByRole('button', { name: 'Email me the link' }));

        await waitFor(() =>
            expect(toast.success).toHaveBeenCalledWith('Export queued. You will receive a download link by email when it is ready.'),
        );
        expect(ask).toHaveBeenCalledTimes(1);
        await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    });

    it("shows the server's own words when it refuses, such as the limit of five an hour, and closes", async () => {
        const ask = vi.fn().mockRejectedValue(new ApiError('You can export at most 5 times per hour.', 429));
        const user = userEvent.setup();
        show(ask);

        await user.click(screen.getByRole('button', { name: 'Export list' }));
        await user.click(await screen.findByRole('button', { name: 'Email me the link' }));

        await waitFor(() => expect(toast.error).toHaveBeenCalledWith('You can export at most 5 times per hour.'));
        expect(toast.success).not.toHaveBeenCalled();
        await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    });

    it('asks for nothing when the person cancels', async () => {
        const ask = vi.fn();
        const user = userEvent.setup();
        show(ask);

        await user.click(screen.getByRole('button', { name: 'Export list' }));
        await user.click(await screen.findByRole('button', { name: 'Cancel' }));

        expect(ask).not.toHaveBeenCalled();
        await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    });

    it('cannot be asked for again while the request is on its way', async () => {
        const ask = vi.fn(() => new Promise<ExportQueued>(() => undefined));
        const user = userEvent.setup();
        show(ask);

        await user.click(screen.getByRole('button', { name: 'Export list' }));
        await user.click(await screen.findByRole('button', { name: 'Email me the link' }));

        await waitFor(() => expect(screen.getByRole('button', { name: 'Export list' })).toBeDisabled());
        expect(ask).toHaveBeenCalledTimes(1);
    });
});
