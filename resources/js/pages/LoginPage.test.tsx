import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTES } from '@/constants/routes';
import { LoginPage } from '@/pages/LoginPage';
import { authApi } from '@/services/api/auth';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';
import type { AuthSession } from '@/types/api';

vi.mock('@/services/api/auth');

const session: AuthSession = {
    token: '1|abc',
    token_type: 'Bearer',
    user: { id: '01', name: 'Akosua', phone: '+233244123456', email: null, roles: ['church-admin'], permissions: [] },
    church: { id: '02', name: 'Grace Assembly', slug: 'grace', plan: 'starter', sms_credits: 0 },
};

function renderLogin() {
    const router = createMemoryRouter(
        [
            { path: ROUTES.LOGIN, element: <LoginPage /> },
            { path: ROUTES.DASHBOARD, element: <p>Dashboard reached</p> },
        ],
        { initialEntries: [ROUTES.LOGIN] },
    );

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

describe('LoginPage', () => {
    beforeEach(() => {
        useAuthStore.setState({ token: null, user: null });
        useChurchStore.setState({ church: null });
    });

    it('signs in with a phone number and a code, then opens the dashboard', async () => {
        vi.mocked(authApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        vi.mocked(authApi.verifyOtp).mockResolvedValue(session);
        const user = userEvent.setup();
        renderLogin();

        await user.type(screen.getByLabelText('Phone number'), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Send code' }));

        await user.type(await screen.findByLabelText('Code'), '123456');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('Dashboard reached')).toBeInTheDocument();
        expect(authApi.verifyOtp).toHaveBeenCalledWith('0244123456', '123456');
        expect(useAuthStore.getState().token).toBe('1|abc');
        expect(useChurchStore.getState().church?.name).toBe('Grace Assembly');
    });

    it('shows a message and does not call the API for an invalid phone number', async () => {
        const user = userEvent.setup();
        renderLogin();

        await user.type(screen.getByLabelText('Phone number'), '12');
        await user.click(screen.getByRole('button', { name: 'Send code' }));

        expect(await screen.findByText('Enter a valid phone number')).toBeInTheDocument();
        expect(authApi.requestOtp).not.toHaveBeenCalled();
    });

    it('requires a 6-digit code', async () => {
        vi.mocked(authApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        const user = userEvent.setup();
        renderLogin();
        await user.type(screen.getByLabelText('Phone number'), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Send code' }));

        await user.type(await screen.findByLabelText('Code'), '12');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('Enter the 6-digit code')).toBeInTheDocument();
        expect(authApi.verifyOtp).not.toHaveBeenCalled();
    });

    it('shows the server message when the code is wrong', async () => {
        vi.mocked(authApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        vi.mocked(authApi.verifyOtp).mockRejectedValue(new ApiError('Invalid code', 422));
        const user = userEvent.setup();
        renderLogin();
        await user.type(screen.getByLabelText('Phone number'), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Send code' }));

        await user.type(await screen.findByLabelText('Code'), '000000');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('Invalid code')).toBeInTheDocument();
        expect(useAuthStore.getState().token).toBeNull();
    });

    it('shows the rate-limit message when too many codes are requested', async () => {
        vi.mocked(authApi.requestOtp).mockRejectedValue(new ApiError('Too many attempts. Try again in 15 minutes.', 429));
        const user = userEvent.setup();
        renderLogin();

        await user.type(screen.getByLabelText('Phone number'), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Send code' }));

        expect(await screen.findByText('Too many attempts. Try again in 15 minutes.')).toBeInTheDocument();
    });

    it('lets the user go back and change the number', async () => {
        vi.mocked(authApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        const user = userEvent.setup();
        renderLogin();
        await user.type(screen.getByLabelText('Phone number'), '0244123456');
        await user.click(screen.getByRole('button', { name: 'Send code' }));

        await user.click(await screen.findByRole('button', { name: 'Use a different number' }));

        await waitFor(() => expect(screen.getByLabelText('Phone number')).toBeInTheDocument());
    });

    it('skips the form when already signed in', () => {
        useAuthStore.setState({ token: '1|abc' });
        renderLogin();

        expect(screen.getByText('Dashboard reached')).toBeInTheDocument();
    });
});
