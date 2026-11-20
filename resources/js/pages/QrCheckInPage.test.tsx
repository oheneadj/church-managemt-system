import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTE_PATTERNS } from '@/constants/routes';
import { QrCheckInPage } from '@/pages/QrCheckInPage';
import { ApiError } from '@/services/ApiError';
import { checkinApi } from '@/services/api/checkin';
import { checkinService, qrCheckedIn } from '@/test/fixtures';

vi.mock('@/services/api/checkin');

const TOKEN = 'a'.repeat(32);

function open() {
    const router = createMemoryRouter([{ path: ROUTE_PATTERNS.CHECKIN, element: <QrCheckInPage /> }], {
        initialEntries: [`/checkin/${TOKEN}`],
    });

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

describe('QrCheckInPage', () => {
    beforeEach(() => {
        vi.mocked(checkinApi.service).mockResolvedValue(checkinService());
        vi.mocked(checkinApi.requestCode).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
    });

    it('names the service, then takes the phone number, the texted code and welcomes the member', async () => {
        vi.mocked(checkinApi.confirm).mockResolvedValue(qrCheckedIn());
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Check in to Sunday Morning Service' })).toBeInTheDocument();
        await user.type(screen.getByLabelText(/Phone number/), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Text me a code' }));
        await user.type(await screen.findByLabelText(/^Code/), '123456');
        await user.click(screen.getByRole('button', { name: 'Check me in' }));

        expect(await screen.findByRole('heading', { level: 1, name: 'Welcome, Emmanuel!' })).toBeInTheDocument();
        expect(checkinApi.requestCode).toHaveBeenCalledWith(TOKEN, '0244123456');
        expect(checkinApi.confirm).toHaveBeenCalledWith(TOKEN, '0244123456', '123456');
    });

    it('says so when the member was already checked in', async () => {
        vi.mocked(checkinApi.confirm).mockResolvedValue(qrCheckedIn({ already_checked_in: true }));
        const user = userEvent.setup();
        open();

        await user.type(await screen.findByLabelText(/Phone number/), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Text me a code' }));
        await user.type(await screen.findByLabelText(/^Code/), '123456');
        await user.click(screen.getByRole('button', { name: 'Check me in' }));

        expect(await screen.findByText(/already checked in/)).toBeInTheDocument();
    });

    it('checks the phone number before asking for a code', async () => {
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Text me a code' }));

        expect(await screen.findByText('Enter a valid phone number')).toBeInTheDocument();
        expect(checkinApi.requestCode).not.toHaveBeenCalled();
    });

    it('puts a wrong-code message beside the code box', async () => {
        vi.mocked(checkinApi.confirm).mockRejectedValue(new ApiError('Invalid code', 422));
        const user = userEvent.setup();
        open();

        await user.type(await screen.findByLabelText(/Phone number/), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Text me a code' }));
        await user.type(await screen.findByLabelText(/^Code/), '000000');
        await user.click(screen.getByRole('button', { name: 'Check me in' }));

        await waitFor(() => expect(screen.getByLabelText(/^Code/)).toHaveAccessibleDescription('Invalid code'));
    });

    it('lets the member go back and use a different number', async () => {
        const user = userEvent.setup();
        open();

        await user.type(await screen.findByLabelText(/Phone number/), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Text me a code' }));
        await user.click(await screen.findByRole('button', { name: 'Use a different number' }));

        expect(await screen.findByLabelText(/Phone number/)).toBeInTheDocument();
    });

    it('tells the member to scan again when the code on the screen has expired', async () => {
        vi.mocked(checkinApi.service).mockRejectedValue(new ApiError('expired', 404));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('This code has expired. Please scan the screen again.');
        expect(screen.queryByLabelText(/Phone number/)).not.toBeInTheDocument();
    });

    it('says check-in is closed when the service is not open', async () => {
        vi.mocked(checkinApi.service).mockResolvedValue(checkinService({ is_open: false }));
        open();

        expect(await screen.findByText('Check-in is closed for this service.')).toBeInTheDocument();
        expect(screen.queryByLabelText(/Phone number/)).not.toBeInTheDocument();
    });
});
