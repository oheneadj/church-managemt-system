import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SmsCreditBalance } from '@/components/layout/SmsCreditBalance';
import { LOW_SMS_CREDITS } from '@/constants/credits';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { authApi } from '@/services/api/auth';
import { renderPage } from '@/test/render';
import type { Session } from '@/types/api';

vi.mock('@/services/api/auth');

/** What "who am I" returns when the church has this many credits. */
function session(credits: number): Session {
    return {
        user: { id: 'user-1', name: 'Akosua Mensah', phone: '+233244123456', email: null, roles: [], permissions: [] },
        church: { id: 'church-1', name: 'Grace Assembly', slug: 'grace', plan: 'starter', sms_credits: credits },
    };
}

function show(credits: number, permissions: Permission[] = []) {
    vi.mocked(authApi.me).mockResolvedValue(session(credits));

    return renderPage(<SmsCreditBalance />, { path: '/', url: '/', permissions });
}

describe('SmsCreditBalance', () => {
    beforeEach(() => vi.clearAllMocks());

    it("shows the church's balance as the server reports it", async () => {
        show(1250);

        expect(await screen.findByText('SMS credits')).toBeInTheDocument();
        expect(await screen.findByText('1,250')).toBeInTheDocument();
        expect(screen.queryByText('Running low')).not.toBeInTheDocument();
    });

    it('shows the balance from the session straight away, then the fresh one from the server', async () => {
        // renderPage starts the church with 0 credits in the session; the server says 420.
        show(420);

        expect(await screen.findByText('420')).toBeInTheDocument();
        expect(authApi.me).toHaveBeenCalled();
    });

    it('warns in words when the balance is at or under the low-credit limit', async () => {
        show(LOW_SMS_CREDITS);

        expect(await screen.findByText('Running low')).toBeInTheDocument();
    });

    it('does not warn one credit above the limit', async () => {
        show(LOW_SMS_CREDITS + 1);

        await screen.findByText(String(LOW_SMS_CREDITS + 1));
        expect(screen.queryByText('Running low')).not.toBeInTheDocument();
    });

    it('says plainly what happens when there are no credits left', async () => {
        show(0);

        expect(await screen.findByText('None left')).toBeInTheDocument();
        expect(screen.getByText(/gift receipts and other texts are not sent/i)).toBeInTheDocument();
    });

    it('announces the warning to screen readers', async () => {
        show(5);

        // The session starts at 0 credits; wait for the server's balance before reading the status.
        await screen.findByText('5');
        expect(screen.getByRole('status')).toHaveTextContent('Running low');
    });

    it('offers Top up only to staff who manage the church, and says it is coming', async () => {
        const user = userEvent.setup();
        show(5, [PERMISSIONS.CHURCH_SETTINGS]);

        await user.click(await screen.findByRole('button', { name: 'Top up' }));

        const dialog = await screen.findByRole('dialog', { name: 'Top up SMS credits' });
        expect(dialog).toHaveTextContent(/coming soon/i);
    });

    it('closes the Top up notice with its Got it button, leaving one control named Close', async () => {
        const user = userEvent.setup();
        show(5, [PERMISSIONS.CHURCH_SETTINGS]);

        await user.click(await screen.findByRole('button', { name: 'Top up' }));
        const dialog = await screen.findByRole('dialog', { name: 'Top up SMS credits' });
        expect(within(dialog).getAllByRole('button', { name: 'Close' })).toHaveLength(1);

        await user.click(within(dialog).getByRole('button', { name: 'Got it' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });

    it('has no Top up button for other staff', async () => {
        show(5, [PERMISSIONS.GIVING_VIEW]);

        await screen.findByText('Running low');
        expect(screen.queryByRole('button', { name: 'Top up' })).not.toBeInTheDocument();
    });

    it('keeps showing the balance it has when the server cannot be reached', async () => {
        vi.mocked(authApi.me).mockRejectedValue(new Error('offline'));
        renderPage(<SmsCreditBalance />, { path: '/', url: '/', permissions: [] });

        expect(await screen.findByText('SMS credits')).toBeInTheDocument();
        expect(screen.getByText('None left')).toBeInTheDocument();
    });
});
