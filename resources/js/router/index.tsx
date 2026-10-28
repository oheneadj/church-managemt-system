import { createBrowserRouter, Navigate } from 'react-router';
import { AppLayout } from '@/components/layout/AppLayout';
import { AuthGuard } from '@/components/layout/AuthGuard';
import { PageSpinner } from '@/components/layout/PageSpinner';
import { ROUTES } from '@/constants/routes';

/**
 * All routes live here. Pages load on demand so the first screen stays small on a slow connection.
 * Private pages sit under AuthGuard; anything unknown ends at the not-found page.
 */
export const router = createBrowserRouter([
    {
        hydrateFallbackElement: <PageSpinner />,
        children: [
            {
                path: ROUTES.LOGIN,
                lazy: async () => ({ Component: (await import('@/pages/LoginPage')).LoginPage }),
            },
            {
                Component: AuthGuard,
                children: [
                    {
                        Component: AppLayout,
                        children: [
                            {
                                path: ROUTES.DASHBOARD,
                                lazy: async () => ({ Component: (await import('@/pages/DashboardPage')).DashboardPage }),
                            },
                            {
                                path: ROUTES.MEMBERS,
                                lazy: async () => ({ Component: (await import('@/pages/MembersPage')).MembersPage }),
                            },
                        ],
                    },
                ],
            },
            { path: ROUTES.HOME, element: <Navigate to={ROUTES.DASHBOARD} replace /> },
            {
                path: '*',
                lazy: async () => ({ Component: (await import('@/pages/NotFoundPage')).NotFoundPage }),
            },
        ],
    },
]);
