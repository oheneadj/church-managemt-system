import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { toast } from 'sonner';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { BroadcastPage } from '@/pages/BroadcastPage';
import { departmentsApi } from '@/services/api/departments';
import { messagesApi } from '@/services/api/messages';
import { ApiError } from '@/services/ApiError';
import { pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';
import type { BroadcastEstimate } from '@/types/messages';

vi.mock('@/services/api/messages');
vi.mock('@/services/api/departments');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

/** The estimate waits for typing to pause, so a test allows a little longer than the pause itself. */
const SETTLE = { timeout: 3000 };

const enough: BroadcastEstimate = { recipients: 48, parts_per_message: 1, credits_needed: 48, credit_balance: 50, enough_credits: true };

function open() {
    return renderPage(<BroadcastPage />, { path: ROUTES.BROADCAST_NEW, url: ROUTES.BROADCAST_NEW, permissions: [PERMISSIONS.SMS_SEND] });
}

async function write(user: ReturnType<typeof userEvent.setup>, text = 'Service is at 9am.') {
    await user.type(await screen.findByLabelText(/^Message/), text);
}

describe('BroadcastPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(departmentsApi.list).mockResolvedValue(
            pageOf([{ id: 'dept-1', name: 'Youth', description: null, head_user_id: null, is_active: true }]),
        );
        vi.mocked(messagesApi.estimate).mockResolvedValue(enough);
    });

    it('asks for nothing until there is a message, and says what it will show', async () => {
        open();

        expect(await screen.findByText(/Write your message to see who it reaches/)).toBeInTheDocument();
        expect(messagesApi.estimate).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Review and send' })).toBeDisabled();
    });

    it('shows who the message reaches and what it costs once the typing pauses', async () => {
        const user = userEvent.setup();
        open();

        await write(user);

        expect(
            await screen.findByText(/This message will be sent to 48 members and use about 48 SMS credits\./, {}, SETTLE),
        ).toBeInTheDocument();
        expect(screen.getByText('You have 50 SMS credits.')).toBeInTheDocument();
        expect(messagesApi.estimate).toHaveBeenLastCalledWith({
            channel: 'sms',
            message: 'Service is at 9am.',
            recipient_filter: { status: 'active' },
        });
    });

    it('costs the department when one is chosen', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByRole('option', { name: 'Youth' });

        await user.selectOptions(screen.getByLabelText('In the department'), 'dept-1');
        await write(user);

        await waitFor(
            () =>
                expect(messagesApi.estimate).toHaveBeenLastCalledWith({
                    channel: 'sms',
                    message: 'Service is at 9am.',
                    recipient_filter: { status: 'active', department_id: 'dept-1' },
                }),
            SETTLE,
        );
    });

    it('says in words when the church cannot pay, and will not send', async () => {
        vi.mocked(messagesApi.estimate).mockResolvedValue({
            ...enough,
            recipients: 60,
            credits_needed: 60,
            credit_balance: 30,
            enough_credits: false,
        });
        const user = userEvent.setup();
        open();

        await write(user);

        expect(await screen.findByText(/which is not enough/, {}, SETTLE)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Review and send' })).toBeDisabled();
    });

    it('says when nobody would be reached, and will not send', async () => {
        vi.mocked(messagesApi.estimate).mockResolvedValue({ ...enough, recipients: 0, credits_needed: 0 });
        const user = userEvent.setup();
        open();

        await write(user);

        expect(await screen.findByText(/there is nobody to send this to/, {}, SETTLE)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Review and send' })).toBeDisabled();
    });

    it("shows the server's reason when the message cannot be costed, such as being too long", async () => {
        vi.mocked(messagesApi.estimate).mockRejectedValue(
            new ApiError('Invalid', 422, { message: ['The message may use at most 4 parts.'] }),
        );
        const user = userEvent.setup();
        open();

        await write(user);

        expect(await screen.findByText('The message may use at most 4 parts.', {}, SETTLE)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Review and send' })).toBeDisabled();
    });

    it('asks before sending, then sends with one key and goes to the message log', async () => {
        vi.mocked(messagesApi.send).mockResolvedValue({
            queued: true,
            broadcast_id: 'b-1',
            estimated_recipients: 48,
            estimated_credit_cost: 48,
            current_credit_balance: 50,
        });
        const user = userEvent.setup();
        const { router } = open();
        await write(user);
        await screen.findByText(/This message will be sent to 48 members/, {}, SETTLE);

        await user.click(screen.getByRole('button', { name: 'Review and send' }));
        const dialog = await screen.findByRole('alertdialog');
        expect(dialog).toHaveTextContent('It will be sent to 48 members and use about 48 SMS credits.');
        expect(messagesApi.send).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Send message' }));

        await waitFor(() => expect(messagesApi.send).toHaveBeenCalledTimes(1));
        const [input, key] = vi.mocked(messagesApi.send).mock.calls[0] ?? [];
        expect(input).toEqual({ channel: 'sms', message: 'Service is at 9am.', recipient_filter: { status: 'active' } });
        expect(key).toMatch(/^[0-9a-f]{32}$/);
        await waitFor(() => expect(router.state.location.pathname).toBe(ROUTES.MESSAGES));
        expect(toast.success).toHaveBeenCalledWith('Your message is on its way to 48 members.');
    });

    it('sends nothing when the person cancels the question', async () => {
        const user = userEvent.setup();
        open();
        await write(user);
        await screen.findByText(/This message will be sent to 48 members/, {}, SETTLE);

        await user.click(screen.getByRole('button', { name: 'Review and send' }));
        await user.click(await screen.findByRole('button', { name: 'Cancel' }));

        expect(messagesApi.send).not.toHaveBeenCalled();
    });

    it('closes the question and tells the person when the server refuses', async () => {
        vi.mocked(messagesApi.send).mockRejectedValue(new ApiError('You can send at most 10 broadcasts per hour.', 429));
        const user = userEvent.setup();
        open();
        await write(user);
        await screen.findByText(/This message will be sent to 48 members/, {}, SETTLE);

        await user.click(screen.getByRole('button', { name: 'Review and send' }));
        await user.click(await screen.findByRole('button', { name: 'Send message' }));

        await waitFor(() => expect(toast.error).toHaveBeenCalledWith('You can send at most 10 broadcasts per hour.'));
        expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    });
});
