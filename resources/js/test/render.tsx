import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import type { ReactElement } from 'react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';
import type { Permission } from '@/constants/permissions';

interface RenderPageOptions {
    /** The route pattern the page is mounted on, for example "/members/:id". */
    path: string;
    /** The address to open, including any query string. */
    url: string;
    /** Permissions the signed-in test user holds. */
    permissions?: Permission[];
}

/**
 * Renders a page the way the app does: inside a query client, a router at the given address and a
 * signed-in user. Other routes render a marker, so a test can check where the page navigated to.
 */
export function renderPage(page: ReactElement, { path, url, permissions = [] }: RenderPageOptions) {
    useAuthStore.setState({
        user: { id: 'user-1', name: 'Akosua Mensah', phone: '+233244123456', email: null, roles: [], permissions },
    });
    useChurchStore.setState({ church: { id: 'church-1', name: 'Grace Assembly', slug: 'grace', plan: 'starter', sms_credits: 0 } });

    const router = createMemoryRouter(
        [
            { path, element: page },
            { path: '*', element: <p>Navigated elsewhere</p> },
        ],
        { initialEntries: [url] },
    );

    return {
        router,
        ...render(
            <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}>
                <RouterProvider router={router} />
            </QueryClientProvider>,
        ),
    };
}
