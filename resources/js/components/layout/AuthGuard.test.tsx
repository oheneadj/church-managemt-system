import { render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it } from 'vitest';
import { AuthGuard } from '@/components/layout/AuthGuard';
import { ROUTES } from '@/constants/routes';
import { useAuthStore } from '@/stores/useAuthStore';
import type { User } from '@/types/api';

const user: User = { id: '01', name: 'Akosua', phone: '+233244123456', email: null, roles: [], permissions: [] };

function renderGuarded() {
    const router = createMemoryRouter(
        [
            { path: ROUTES.LOGIN, element: <p>Login screen</p> },
            { Component: AuthGuard, children: [{ path: ROUTES.DASHBOARD, element: <p>Private page</p> }] },
        ],
        { initialEntries: [ROUTES.DASHBOARD] },
    );

    render(<RouterProvider router={router} />);
}

describe('AuthGuard', () => {
    beforeEach(() => useAuthStore.setState({ user: null }));

    it('sends a signed-out visitor to the login screen', () => {
        renderGuarded();

        expect(screen.getByText('Login screen')).toBeInTheDocument();
        expect(screen.queryByText('Private page')).not.toBeInTheDocument();
    });

    it('lets a signed-in user through', () => {
        useAuthStore.setState({ user });

        renderGuarded();

        expect(screen.getByText('Private page')).toBeInTheDocument();
    });
});
