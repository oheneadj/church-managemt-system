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
import type { Session } from '@/types/api';

vi.mock('@/services/api/auth');

const session: Session = {
    user: { id: '01', name: 'Akosua', phone: '+233244123456', email: 'akosua@example.com', roles: ['church-admin'], permissions: [] },
    church: { id: '02', name: 'Grace Assembly', slug: 'grace', plan: 'starter', sms_credits: 0 },
};

const codeSent = { message: 'Code sent', expires_in: 600 };

function renderLogin() {
    const router = createMemoryRouter(
        [
            { path: ROUTES.LOGIN, element: <LoginPage /> },
            { path: ROUTES.FORGOT_PASSWORD, element: <p>Forgot page reached</p> },
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

async function enterCredentials(
    user: ReturnType<typeof userEvent.setup>,
    email = 'Akosua@Example.com',
    password = 'a long passphrase here',
) {
    await user.type(screen.getByLabelText(/Email address/), email);
    await user.type(screen.getByLabelText(/Password/), password);
    await user.click(screen.getByRole('button', { name: 'Continue' }));
}

describe('LoginPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        useAuthStore.setState({ user: null });
        useChurchStore.setState({ church: null });
    });

    it('signs in with email and password, then the emailed code, and opens the dashboard', async () => {
        vi.mocked(authApi.login).mockResolvedValue(codeSent);
        vi.mocked(authApi.verifyLogin).mockResolvedValue(session);
        const user = userEvent.setup();
        renderLogin();

        await enterCredentials(user);
        await user.type(await screen.findByLabelText(/Code/), '123456');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('Dashboard reached')).toBeInTheDocument();
        expect(authApi.login).toHaveBeenCalledWith('akosua@example.com', 'a long passphrase here');
        expect(authApi.verifyLogin).toHaveBeenCalledWith('akosua@example.com', '123456');
        expect(useAuthStore.getState().user?.name).toBe('Akosua');
        expect(useChurchStore.getState().church?.name).toBe('Grace Assembly');
    });

    it('says where the code was sent and does not keep the password after step 1', async () => {
        vi.mocked(authApi.login).mockResolvedValue(codeSent);
        const user = userEvent.setup();
        renderLogin();

        await enterCredentials(user);

        expect(await screen.findByText(/akosua@example.com/)).toBeInTheDocument();
        expect(screen.queryByLabelText(/Password/)).not.toBeInTheDocument();
    });

    it('checks the email and that a password was typed before calling the API', async () => {
        const user = userEvent.setup();
        renderLogin();

        await user.type(screen.getByLabelText(/Email address/), 'not-an-email');
        await user.click(screen.getByRole('button', { name: 'Continue' }));

        expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
        expect(screen.getByText('Enter your password')).toBeInTheDocument();
        expect(authApi.login).not.toHaveBeenCalled();
    });

    it('shows the one generic message for a wrong password', async () => {
        vi.mocked(authApi.login).mockRejectedValue(new ApiError('Invalid email or password', 422));
        const user = userEvent.setup();
        renderLogin();

        await enterCredentials(user);

        expect(await screen.findByText('Invalid email or password')).toBeInTheDocument();
        expect(screen.queryByLabelText(/^Code/)).not.toBeInTheDocument();
    });

    it('shows the lockout message', async () => {
        vi.mocked(authApi.login).mockRejectedValue(new ApiError('Too many attempts. Try again in 15 minutes.', 429));
        const user = userEvent.setup();
        renderLogin();

        await enterCredentials(user);

        expect(await screen.findByText('Too many attempts. Try again in 15 minutes.')).toBeInTheDocument();
    });

    it('requires a 6-digit code', async () => {
        vi.mocked(authApi.login).mockResolvedValue(codeSent);
        const user = userEvent.setup();
        renderLogin();
        await enterCredentials(user);

        await user.type(await screen.findByLabelText(/Code/), '12');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('Enter the 6-digit code')).toBeInTheDocument();
        expect(authApi.verifyLogin).not.toHaveBeenCalled();
    });

    it('shows the server message when the code is wrong and stays signed out', async () => {
        vi.mocked(authApi.login).mockResolvedValue(codeSent);
        vi.mocked(authApi.verifyLogin).mockRejectedValue(new ApiError('Invalid code', 422));
        const user = userEvent.setup();
        renderLogin();
        await enterCredentials(user);

        await user.type(await screen.findByLabelText(/Code/), '000000');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('Invalid code')).toBeInTheDocument();
        expect(useAuthStore.getState().user).toBeNull();
    });

    it('lets the user go back and use a different account', async () => {
        vi.mocked(authApi.login).mockResolvedValue(codeSent);
        const user = userEvent.setup();
        renderLogin();
        await enterCredentials(user);

        await user.click(await screen.findByRole('button', { name: 'Use a different account' }));

        await waitFor(() => expect(screen.getByLabelText(/Email address/)).toBeInTheDocument());
    });

    it('links to forgot password', async () => {
        const user = userEvent.setup();
        renderLogin();

        await user.click(screen.getByRole('link', { name: 'Forgot your password?' }));

        expect(await screen.findByText('Forgot page reached')).toBeInTheDocument();
    });

    it('skips the form when already signed in', () => {
        useAuthStore.setState({ user: session.user });
        renderLogin();

        expect(screen.getByText('Dashboard reached')).toBeInTheDocument();
    });
});
