import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PlatformGuard } from '@/components/Platform/PlatformGuard';
import { PlatformHostOnly } from '@/components/Platform/PlatformHostOnly';
import { ROUTES } from '@/constants/routes';
import { ApiError } from '@/services/ApiError';
import { platformApi } from '@/services/api/platform';
import { platformAdmin } from '@/test/fixtures';

vi.mock('@/services/api/platform');

function mount(url: string) {
    const router = createMemoryRouter(
        [
            { path: ROUTES.PLATFORM_LOGIN, element: <p>Platform sign in</p> },
            {
                Component: PlatformHostOnly,
                children: [{ Component: PlatformGuard, children: [{ path: ROUTES.PLATFORM_PACKAGES, element: <p>The packages</p> }] }],
            },
        ],
        { initialEntries: [url] },
    );
    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return router;
}

describe('the platform guard and host gate', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.stubEnv('VITE_TENANT_BASE_DOMAIN', window.location.hostname);
    });

    it('shows the platform shell and the screen to a signed-in owner, with their name and a sign-out button', async () => {
        vi.mocked(platformApi.me).mockResolvedValue(platformAdmin({ name: 'Platform Owner' }));
        mount(ROUTES.PLATFORM_PACKAGES);

        expect(await screen.findByText('The packages')).toBeInTheDocument();
        expect(screen.getByText('Platform Owner')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Sign out' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'SMS packages' })).toBeInTheDocument();
    });

    it('sends someone who is not signed in to the platform sign-in', async () => {
        vi.mocked(platformApi.me).mockRejectedValue(new ApiError('Unauthenticated.', 401));
        const router = mount(ROUTES.PLATFORM_PACKAGES);

        expect(await screen.findByText('Platform sign in')).toBeInTheDocument();
        await waitFor(() => expect(router.state.location.pathname).toBe(ROUTES.PLATFORM_LOGIN));
    });

    it("is not found on a church's address, and does not even ask the server", async () => {
        vi.stubEnv('VITE_TENANT_BASE_DOMAIN', 'platform.example');
        mount(ROUTES.PLATFORM_PACKAGES);

        expect(await screen.findByText('Page not found')).toBeInTheDocument();
        expect(platformApi.me).not.toHaveBeenCalled();
    });
});
