import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { MessagesPage } from '@/pages/MessagesPage';
import { messagesApi } from '@/services/api/messages';
import { ApiError } from '@/services/ApiError';
import { messageLogLine, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/messages');

function open(url: string = ROUTES.MESSAGES) {
    return renderPage(<MessagesPage />, { path: ROUTES.MESSAGES, url, permissions: [PERMISSIONS.SMS_SEND] });
}

describe('MessagesPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(messagesApi.list).mockResolvedValue(
            pageOf([
                messageLogLine(),
                messageLogLine({
                    id: 'message-2',
                    channel: 'email',
                    channel_label: 'Email',
                    recipient_phone: null,
                    recipient_email: 'kofi@example.com',
                    message_type: 'something_new',
                    content: 'Hello by email.',
                    status: 'failed',
                    status_label: 'Failed',
                    sent_at: null,
                }),
            ]),
        );
    });

    it('lists each message with who got it, what it said, how, what kind and whether it arrived', async () => {
        open();

        const first = (await screen.findByText('+233244123456')).closest('li') as HTMLElement;
        expect(within(first).getByText('Service starts at 9am this Sunday.')).toBeInTheDocument();
        expect(within(first).getByText(/SMS · Broadcast · 5 Nov 2026/)).toBeInTheDocument();
        expect(within(first).getByText('Sent')).toBeInTheDocument();
        expect(messagesApi.list).toHaveBeenCalledWith({ channel: '', type: '', from: '', to: '', page: 1 });
    });

    it('shows an email recipient, a failure, and a kind this app does not know as the server named it', async () => {
        open();

        const second = (await screen.findByText('kofi@example.com')).closest('li') as HTMLElement;
        expect(within(second).getByText('Failed')).toBeInTheDocument();
        expect(within(second).getByText(/Email · something_new/)).toBeInTheDocument();
    });

    it('filters by how it was sent and keeps the choice in the address', async () => {
        const user = userEvent.setup();
        const { router } = open();
        await screen.findByText('+233244123456');

        await user.selectOptions(screen.getByLabelText('Sent by'), 'email');

        await waitFor(() => expect(messagesApi.list).toHaveBeenLastCalledWith({ channel: 'email', type: '', from: '', to: '', page: 1 }));
        expect(router.state.location.search).toBe('?channel=email');
    });

    it('filters by kind and by day, and clears every filter at once', async () => {
        const user = userEvent.setup();
        const { router } = open('/messages?type=broadcast&from=2026-11-01&to=2026-11-30&channel=sms');
        await screen.findByText('+233244123456');

        expect(messagesApi.list).toHaveBeenCalledWith({ channel: 'sms', type: 'broadcast', from: '2026-11-01', to: '2026-11-30', page: 1 });

        await user.click(screen.getByRole('button', { name: 'Clear filters' }));

        await waitFor(() => expect(router.state.location.search).toBe(''));
        expect(messagesApi.list).toHaveBeenLastCalledWith({ channel: '', type: '', from: '', to: '', page: 1 });
    });

    it('ignores values in the address that are not real filters', async () => {
        open('/messages?channel=pigeon&type=nope&from=yesterday');

        await screen.findByText('+233244123456');
        expect(messagesApi.list).toHaveBeenCalledWith({ channel: '', type: '', from: '', to: '', page: 1 });
    });

    it('shows an empty state when nothing was sent', async () => {
        vi.mocked(messagesApi.list).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('No messages found')).toBeInTheDocument();
    });

    it('says what went wrong and offers to try again', async () => {
        vi.mocked(messagesApi.list).mockRejectedValue(new ApiError('The server is busy.', 500));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    });
});
