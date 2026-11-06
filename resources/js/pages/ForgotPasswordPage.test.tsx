import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTES } from '@/constants/routes';
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage';
import { authApi } from '@/services/api/auth';
import { ApiError } from '@/services/ApiError';

vi.mock('@/services/api/auth');

const PASSWORD = 'a brand new passphrase';

function renderPage() {
    const router = createMemoryRouter(
        [
            { path: ROUTES.FORGOT_PASSWORD, element: <ForgotPasswordPage /> },
            { path: ROUTES.LOGIN, element: <p>Sign in reached</p> },
        ],
        { initialEntries: [ROUTES.FORGOT_PASSWORD] },
    );

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

async function askForCode(user: ReturnType<typeof userEvent.setup>) {
    await user.type(screen.getByLabelText(/Email address/), 'Akosua@Example.com');
    await user.click(screen.getByRole('button', { name: 'Email me a code' }));
}

async function fillReset(user: ReturnType<typeof userEvent.setup>, overrides: { code?: string; again?: string } = {}) {
    await user.type(await screen.findByLabelText(/^Code/), overrides.code ?? '123456');
    await user.type(screen.getByLabelText(/^New password/), PASSWORD);
    await user.type(screen.getByLabelText(/Type the new password again/), overrides.again ?? PASSWORD);
    await user.click(screen.getByRole('button', { name: 'Save new password' }));
}

describe('ForgotPasswordPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    it('asks for a code, sets the new password with it and points to sign in', async () => {
        vi.mocked(authApi.forgotPassword).mockResolvedValue({ message: 'sent', expires_in: 600 });
        vi.mocked(authApi.resetPassword).mockResolvedValue(undefined);
        const user = userEvent.setup();
        renderPage();

        await askForCode(user);
        await fillReset(user);

        expect(await screen.findByRole('heading', { name: 'Password changed' })).toBeInTheDocument();
        expect(authApi.forgotPassword).toHaveBeenCalledWith('akosua@example.com');
        expect(authApi.resetPassword).toHaveBeenCalledWith({
            email: 'akosua@example.com',
            code: '123456',
            password: PASSWORD,
            password_confirmation: PASSWORD,
        });
        await user.click(screen.getByRole('link', { name: 'Go to sign in' }));
        expect(await screen.findByText('Sign in reached')).toBeInTheDocument();
    });

    it('does not call the API for an invalid email', async () => {
        const user = userEvent.setup();
        renderPage();

        await user.type(screen.getByLabelText(/Email address/), 'nope');
        await user.click(screen.getByRole('button', { name: 'Email me a code' }));

        expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
        expect(authApi.forgotPassword).not.toHaveBeenCalled();
    });

    it('shows the limit message when too many codes were asked for', async () => {
        vi.mocked(authApi.forgotPassword).mockRejectedValue(new ApiError('Too many requests.', 429));
        const user = userEvent.setup();
        renderPage();

        await askForCode(user);

        expect(await screen.findByText('Too many requests.')).toBeInTheDocument();
    });

    it('checks the password rules in the browser before calling the API', async () => {
        vi.mocked(authApi.forgotPassword).mockResolvedValue({ message: 'sent', expires_in: 600 });
        const user = userEvent.setup();
        renderPage();
        await askForCode(user);

        await user.type(await screen.findByLabelText(/^Code/), '123456');
        await user.type(screen.getByLabelText(/^New password/), 'short');
        await user.type(screen.getByLabelText(/Type the new password again/), 'different');
        await user.click(screen.getByRole('button', { name: 'Save new password' }));

        expect(await screen.findByText(/Use at least 12 characters/)).toBeInTheDocument();
        expect(screen.getByText('The two passwords do not match')).toBeInTheDocument();
        expect(authApi.resetPassword).not.toHaveBeenCalled();
    });

    it('puts a wrong-code message next to the code box', async () => {
        vi.mocked(authApi.forgotPassword).mockResolvedValue({ message: 'sent', expires_in: 600 });
        vi.mocked(authApi.resetPassword).mockRejectedValue(new ApiError('Invalid code', 422));
        const user = userEvent.setup();
        renderPage();
        await askForCode(user);

        await fillReset(user);

        expect(await screen.findByLabelText(/^Code/)).toHaveAccessibleDescription('Invalid code');
    });

    it('puts a server password message next to the password box', async () => {
        vi.mocked(authApi.forgotPassword).mockResolvedValue({ message: 'sent', expires_in: 600 });
        vi.mocked(authApi.resetPassword).mockRejectedValue(
            new ApiError('invalid', 422, { password: ['The password cannot be your email address.'] }),
        );
        const user = userEvent.setup();
        renderPage();
        await askForCode(user);

        await fillReset(user);

        expect(await screen.findByLabelText(/^New password/)).toHaveAccessibleDescription('The password cannot be your email address.');
    });

    it('lets the user go back and use a different email', async () => {
        vi.mocked(authApi.forgotPassword).mockResolvedValue({ message: 'sent', expires_in: 600 });
        const user = userEvent.setup();
        renderPage();
        await askForCode(user);

        await user.click(await screen.findByRole('button', { name: 'Use a different email' }));

        expect(await screen.findByLabelText(/Email address/)).toBeInTheDocument();
    });
});
