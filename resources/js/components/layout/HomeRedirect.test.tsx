import { render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { HomeRedirect } from '@/components/layout/HomeRedirect';
import { ROUTES } from '@/constants/routes';

function renderHome() {
    const router = createMemoryRouter(
        [
            { path: ROUTES.HOME, element: <HomeRedirect /> },
            { path: ROUTES.START, element: <p>Start page</p> },
            { path: ROUTES.DASHBOARD, element: <p>Dashboard</p> },
        ],
        { initialEntries: [ROUTES.HOME] },
    );

    render(<RouterProvider router={router} />);
}

describe('HomeRedirect', () => {
    afterEach(() => vi.unstubAllEnvs());

    it('sends the platform address to the sign-up page', () => {
        vi.stubEnv('VITE_TENANT_BASE_DOMAIN', window.location.hostname);
        renderHome();

        expect(screen.getByText('Start page')).toBeInTheDocument();
    });

    it("sends a church's address to the dashboard", () => {
        vi.stubEnv('VITE_TENANT_BASE_DOMAIN', 'some-other-domain.test');
        renderHome();

        expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });
});
