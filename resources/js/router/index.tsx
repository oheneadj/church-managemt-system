import { createBrowserRouter } from 'react-router';
import { AppLayout } from '@/components/layout/AppLayout';
import { AuthGuard } from '@/components/layout/AuthGuard';
import { HomeRedirect } from '@/components/layout/HomeRedirect';
import { RouteErrorPage } from '@/components/layout/RouteErrorPage';
import { PageSpinner } from '@/components/layout/PageSpinner';
import { ROUTE_PATTERNS, ROUTES } from '@/constants/routes';

/**
 * All routes live here. Pages load on demand so the first screen stays small on a slow connection.
 * Private pages sit under AuthGuard; anything unknown ends at the not-found page.
 */
export const router = createBrowserRouter([
    {
        hydrateFallbackElement: <PageSpinner />,
        errorElement: <RouteErrorPage />,
        children: [
            {
                path: ROUTES.LOGIN,
                lazy: async () => ({ Component: (await import('@/pages/LoginPage')).LoginPage }),
            },
            {
                path: ROUTE_PATTERNS.CHECKIN,
                lazy: async () => ({ Component: (await import('@/pages/QrCheckInPage')).QrCheckInPage }),
            },
            {
                path: ROUTES.START,
                lazy: async () => ({ Component: (await import('@/pages/StartChurchPage')).StartChurchPage }),
            },
            {
                Component: AuthGuard,
                children: [
                    {
                        // The screen at the venue: private, but with none of the menu around it.
                        path: ROUTE_PATTERNS.SERVICE_QR,
                        lazy: async () => ({ Component: (await import('@/pages/ServiceQrPage')).ServiceQrPage }),
                    },
                    {
                        Component: AppLayout,
                        children: [
                            {
                                path: ROUTES.DASHBOARD,
                                lazy: async () => ({ Component: (await import('@/pages/DashboardPage')).DashboardPage }),
                            },
                            {
                                path: ROUTES.SERVICES,
                                lazy: async () => ({ Component: (await import('@/pages/ServicesPage')).ServicesPage }),
                            },
                            {
                                path: ROUTES.GIVING,
                                lazy: async () => ({ Component: (await import('@/pages/GivingPage')).GivingPage }),
                            },
                            {
                                path: ROUTES.SERVICE_SCHEDULES,
                                lazy: async () => ({ Component: (await import('@/pages/ServiceSchedulesPage')).ServiceSchedulesPage }),
                            },
                            {
                                path: ROUTE_PATTERNS.SERVICE,
                                lazy: async () => ({ Component: (await import('@/pages/ServiceDetailPage')).ServiceDetailPage }),
                            },
                            {
                                path: ROUTES.MEMBERS,
                                lazy: async () => ({ Component: (await import('@/pages/MembersPage')).MembersPage }),
                            },
                            {
                                path: ROUTES.FAMILIES,
                                lazy: async () => ({ Component: (await import('@/pages/FamiliesPage')).FamiliesPage }),
                            },
                            {
                                path: ROUTE_PATTERNS.FAMILY,
                                lazy: async () => ({ Component: (await import('@/pages/FamilyDetailPage')).FamilyDetailPage }),
                            },
                            {
                                path: ROUTES.MEMBER_NEW,
                                lazy: async () => ({ Component: (await import('@/pages/MemberFormPage')).MemberFormPage }),
                            },
                            {
                                path: ROUTE_PATTERNS.MEMBER_EDIT,
                                lazy: async () => ({ Component: (await import('@/pages/MemberFormPage')).MemberFormPage }),
                            },
                            {
                                path: ROUTE_PATTERNS.MEMBER,
                                lazy: async () => ({ Component: (await import('@/pages/MemberDetailPage')).MemberDetailPage }),
                            },
                        ],
                    },
                ],
            },
            { path: ROUTES.HOME, element: <HomeRedirect /> },
            {
                path: '*',
                lazy: async () => ({ Component: (await import('@/pages/NotFoundPage')).NotFoundPage }),
            },
        ],
    },
]);
