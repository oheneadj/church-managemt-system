import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTES } from '@/constants/routes';
import { PlatformLoginPage } from '@/pages/PlatformLoginPage';
import { ApiError } from '@/services/ApiError';
import { platformApi } from '@/services/api/platform';
import { platformAdmin } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/platform');

function open() {
    return renderPage(<PlatformLoginPage />, { path: ROUTES.PLATFORM_LOGIN, url: ROUTES.PLATFORM_LOGIN });
}

describe('PlatformLoginPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(platformApi.me).mockRejectedValue(new ApiError('Unauthenticated.', 401));
    });

    it('asks for the password first, then the emailed code, and signs in', async () => {
        vi.mocked(platformApi.login).mockResolvedValue({ message: 'Code sent', expires_in: 600 });
        vi.mocked(platformApi.verifyLogin).mockResolvedValue(platformAdmin());
        const user = userEvent.setup();
        const { router } = open();

        await user.type(await screen.findByLabelText(/^Email address/), 'Owner@Platform.test');
        await user.type(screen.getByLabelText(/^Password/), 'a long platform passphrase');
        await user.click(screen.getByRole('button', { name: 'Continue' }));

        await waitFor(() => expect(platformApi.login).toHaveBeenCalledWith('owner@platform.test', 'a long platform passphrase'));
        expect(await screen.findByText(/We emailed a 6-digit code to owner@platform.test/)).toBeInTheDocument();

        await user.type(screen.getByLabelText(/^Code/), '123456');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        await waitFor(() => expect(platformApi.verifyLogin).toHaveBeenCalledWith('owner@platform.test', '123456'));
        await waitFor(() => expect(router.state.location.pathname).toBe(ROUTES.PLATFORM_HOME));
    });

    it('shows one plain message for a wrong password and keeps the password out of the next step', async () => {
        vi.mocked(platformApi.login).mockRejectedValue(new ApiError('Invalid email or password', 422));
        const user = userEvent.setup();
        open();

        await user.type(await screen.findByLabelText(/^Email address/), 'owner@platform.test');
        await user.type(screen.getByLabelText(/^Password/), 'wrong');
        await user.click(screen.getByRole('button', { name: 'Continue' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Invalid email or password');
        expect(screen.queryByLabelText(/^Code/)).not.toBeInTheDocument();
    });

    it('checks the email and password are filled in before asking the server', async () => {
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Continue' }));

        expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
        expect(screen.getByText('Enter your password')).toBeInTheDocument();
        expect(platformApi.login).not.toHaveBeenCalled();
    });

    it('shows a wrong code beside its box, and lets the person start again with another account', async () => {
        vi.mocked(platformApi.login).mockResolvedValue({ message: 'Code sent', expires_in: 600 });
        vi.mocked(platformApi.verifyLogin).mockRejectedValue(
            new ApiError('The code is not right.', 422, { code: ['The code is not right.'] }),
        );
        const user = userEvent.setup();
        open();
        await user.type(await screen.findByLabelText(/^Email address/), 'owner@platform.test');
        await user.type(screen.getByLabelText(/^Password/), 'a long platform passphrase');
        await user.click(screen.getByRole('button', { name: 'Continue' }));
        await user.type(await screen.findByLabelText(/^Code/), '000000');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        expect((await screen.findAllByText('The code is not right.')).length).toBeGreaterThan(0);

        await user.click(screen.getByRole('button', { name: 'Use a different account' }));
        expect(await screen.findByLabelText(/^Email address/)).toBeInTheDocument();
    });

    it('goes straight on when the owner is already signed in', async () => {
        vi.mocked(platformApi.me).mockResolvedValue(platformAdmin());
        const { router } = open();

        await waitFor(() => expect(router.state.location.pathname).toBe(ROUTES.PLATFORM_HOME));
    });
});
