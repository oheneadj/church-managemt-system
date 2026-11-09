import { createBrowserRouter, Navigate } from 'react-router';
import { PlatformGuard } from '@/components/Platform/PlatformGuard';
import { PlatformHostOnly } from '@/components/Platform/PlatformHostOnly';
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
                path: ROUTES.FORGOT_PASSWORD,
                lazy: async () => ({ Component: (await import('@/pages/ForgotPasswordPage')).ForgotPasswordPage }),
            },
            {
                path: ROUTES.CHOOSE_PASSWORD,
                lazy: async () => ({ Component: (await import('@/pages/ChoosePasswordPage')).ChoosePasswordPage }),
            },
            {
                path: ROUTE_PATTERNS.CHECKIN,
                lazy: async () => ({ Component: (await import('@/pages/QrCheckInPage')).QrCheckInPage }),
            },
            {
                // The platform owner's side exists only on the platform host; on a church's address these are not found.
                Component: PlatformHostOnly,
                children: [
                    {
                        path: ROUTES.PLATFORM_LOGIN,
                        lazy: async () => ({ Component: (await import('@/pages/PlatformLoginPage')).PlatformLoginPage }),
                    },
                    {
                        Component: PlatformGuard,
                        children: [
                            { path: ROUTES.PLATFORM_HOME, element: <Navigate to={ROUTES.PLATFORM_PACKAGES} replace /> },
                            {
                                path: ROUTES.PLATFORM_PACKAGES,
                                lazy: async () => ({ Component: (await import('@/pages/PlatformPackagesPage')).PlatformPackagesPage }),
                            },
                        ],
                    },
                ],
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
                                path: ROUTES.STAFF,
                                lazy: async () => ({ Component: (await import('@/pages/StaffPage')).StaffPage }),
                            },
                            {
                                path: ROUTES.ABSENT_MEMBERS,
                                lazy: async () => ({ Component: (await import('@/pages/AbsentMembersPage')).AbsentMembersPage }),
                            },
                            {
                                path: ROUTES.REPORTS,
                                lazy: async () => ({ Component: (await import('@/pages/ReportsPage')).ReportsPage }),
                            },
                            {
                                path: ROUTES.BROADCAST_NEW,
                                lazy: async () => ({ Component: (await import('@/pages/BroadcastPage')).BroadcastPage }),
                            },
                            {
                                path: ROUTES.MESSAGES,
                                lazy: async () => ({ Component: (await import('@/pages/MessagesPage')).MessagesPage }),
                            },
                            {
                                path: ROUTES.FUNDS,
                                lazy: async () => ({ Component: (await import('@/pages/FundTypesPage')).FundTypesPage }),
                            },
                            {
                                path: ROUTES.SETTINGS,
                                lazy: async () => ({ Component: (await import('@/pages/SettingsPage')).SettingsPage }),
                            },
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
                                path: ROUTES.PLEDGES,
                                lazy: async () => ({ Component: (await import('@/pages/PledgesPage')).PledgesPage }),
                            },
                            {
                                path: ROUTES.SERVICE_SCHEDULES,
                                lazy: async () => ({ Component: (await import('@/pages/ServiceSchedulesPage')).ServiceSchedulesPage }),
                            },
                            {
                                path: ROUTE_PATTERNS.SERVICE_OFFERING,
                                lazy: async () => ({ Component: (await import('@/pages/OfferingReportPage')).OfferingReportPage }),
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
                                path: ROUTES.DEPARTMENTS,
                                lazy: async () => ({ Component: (await import('@/pages/DepartmentsPage')).DepartmentsPage }),
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
            // The component showcase is for people building the app. The condition is written out here, not hidden in a function, so a
            // production build can see it is false and leave the whole page out of the bundle (scripts/check-showcase-excluded.sh checks).
            ...(import.meta.env.DEV || import.meta.env.VITE_DESIGN_SYSTEM_SHOWCASE === 'true'
                ? [
                      {
                          path: ROUTES.DESIGN_SYSTEM,
                          lazy: async () => ({ Component: (await import('@/pages/DesignSystemShowcasePage')).DesignSystemShowcasePage }),
                      },
                  ]
                : []),
            { path: ROUTES.HOME, element: <HomeRedirect /> },
            {
                path: '*',
                lazy: async () => ({ Component: (await import('@/pages/NotFoundPage')).NotFoundPage }),
            },
        ],
    },
]);
