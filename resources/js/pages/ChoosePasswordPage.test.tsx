import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTES } from '@/constants/routes';
import { ChoosePasswordPage } from '@/pages/ChoosePasswordPage';
import { authApi } from '@/services/api/auth';
import { ApiError } from '@/services/ApiError';

vi.mock('@/services/api/auth');

const PASSWORD = 'a brand new passphrase';

function renderPage() {
    const router = createMemoryRouter(
        [
            { path: ROUTES.CHOOSE_PASSWORD, element: <ChoosePasswordPage /> },
            { path: ROUTES.LOGIN, element: <p>Sign in reached</p> },
        ],
        { initialEntries: [ROUTES.CHOOSE_PASSWORD] },
    );

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

async function fill(user: ReturnType<typeof userEvent.setup>, overrides: { email?: string; code?: string; again?: string } = {}) {
    await user.type(screen.getByLabelText(/^Email address/), overrides.email ?? 'Yaw@Example.com');
    await user.type(screen.getByLabelText(/^Code/), overrides.code ?? '123456');
    await user.type(screen.getByLabelText(/^New password/), PASSWORD);
    await user.type(screen.getByLabelText(/Type the new password again/), overrides.again ?? PASSWORD);
    await user.click(screen.getByRole('button', { name: 'Save password' }));
}

describe('ChoosePasswordPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    it('sets the first password with the emailed invitation code and points to sign in', async () => {
        vi.mocked(authApi.resetPassword).mockResolvedValue(undefined);
        const user = userEvent.setup();
        renderPage();

        await fill(user);

        expect(await screen.findByRole('heading', { name: 'Password saved' })).toBeInTheDocument();
        expect(authApi.resetPassword).toHaveBeenCalledWith({
            email: 'yaw@example.com',
            code: '123456',
            password: PASSWORD,
            password_confirmation: PASSWORD,
        });
        await user.click(screen.getByRole('link', { name: 'Go to sign in' }));
        expect(await screen.findByText('Sign in reached')).toBeInTheDocument();
    });

    it('says what the page is for', () => {
        renderPage();

        expect(screen.getByRole('heading', { level: 1, name: 'Choose your password' })).toBeInTheDocument();
        expect(screen.getByText(/invitation/i)).toBeInTheDocument();
    });

    it('checks the email, the code and the password rules before calling the API', async () => {
        const user = userEvent.setup();
        renderPage();

        await user.type(screen.getByLabelText(/^Email address/), 'nope');
        await user.type(screen.getByLabelText(/^Code/), '12');
        await user.type(screen.getByLabelText(/^New password/), 'short');
        await user.type(screen.getByLabelText(/Type the new password again/), 'different');
        await user.click(screen.getByRole('button', { name: 'Save password' }));

        expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
        expect(screen.getByText('Enter the 6-digit code')).toBeInTheDocument();
        expect(screen.getByText(/Use at least 12 characters/)).toBeInTheDocument();
        expect(screen.getByText('The two passwords do not match')).toBeInTheDocument();
        expect(authApi.resetPassword).not.toHaveBeenCalled();
    });

    it('puts a wrong-code message next to the code box', async () => {
        vi.mocked(authApi.resetPassword).mockRejectedValue(new ApiError('Invalid code', 422));
        const user = userEvent.setup();
        renderPage();

        await fill(user);

        expect(await screen.findByLabelText(/^Code/)).toHaveAccessibleDescription('Invalid code');
    });

    it('puts a server password message next to the password box', async () => {
        vi.mocked(authApi.resetPassword).mockRejectedValue(
            new ApiError('invalid', 422, { password: ['The password cannot be your email address.'] }),
        );
        const user = userEvent.setup();
        renderPage();

        await fill(user);

        expect(await screen.findByLabelText(/^New password/)).toHaveAccessibleDescription('The password cannot be your email address.');
    });

    it('links back to sign in', () => {
        renderPage();

        expect(screen.getByRole('link', { name: 'Back to sign in' })).toBeInTheDocument();
    });
});
