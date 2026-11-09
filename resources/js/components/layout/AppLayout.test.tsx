import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AppLayout } from '@/components/layout/AppLayout';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { authApi } from '@/services/api/auth';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

vi.mock('@/services/api/auth');

// The signed-in shell (docs/design-system): a branded sidebar with an icon menu, a header with the credit balance and the person,
// and the page area. Staff see only the links they may use, and every link stays visible on a phone.

function open(permissions: Permission[] = [], path = '/members') {
    useAuthStore.setState({ user: { id: 'u1', name: 'Akosua Mensah', phone: '+233244123456', email: null, roles: [], permissions } });
    useChurchStore.setState({ church: { id: 'c1', name: 'Grace Assembly', slug: 'grace', plan: 'starter', sms_credits: 120 } });
    const router = createMemoryRouter(
        [
            {
                path: '/',
                element: <AppLayout />,
                children: [
                    { path: 'members', element: <p>Members page</p> },
                    { path: 'dashboard', element: <p>Dashboard page</p> },
                ],
            },
        ],
        { initialEntries: [path] },
    );

    return render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

const links = () =>
    within(screen.getByRole('navigation', { name: 'Main' }))
        .getAllByRole('link')
        .map((link) => link.textContent);

describe('AppLayout', () => {
    beforeEach(() => vi.resetAllMocks());

    it('shows the church mark with its initials, and the name', () => {
        open();

        expect(screen.getByText('Grace Assembly')).toBeInTheDocument();
        expect(screen.getByText('GA')).toHaveAttribute('aria-hidden', 'true');
    });

    it('shows only the links the person may use', () => {
        open();

        expect(links()).toEqual(['Dashboard', 'Members', 'Families', 'Services']);
    });

    it('shows every link to someone who holds every permission', () => {
        open([
            PERMISSIONS.GIVING_VIEW,
            PERMISSIONS.REPORTS_VIEW,
            PERMISSIONS.SMS_SEND,
            PERMISSIONS.STAFF_MANAGE,
            PERMISSIONS.CHURCH_SETTINGS,
        ]);

        expect(links()).toEqual([
            'Dashboard',
            'Members',
            'Families',
            'Services',
            'Giving',
            'Pledges',
            'Reports',
            'Messages',
            'Staff',
            'Settings',
        ]);
    });

    it('gives each link an icon that screen readers skip', () => {
        open();

        for (const link of within(screen.getByRole('navigation', { name: 'Main' })).getAllByRole('link')) {
            expect(link.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
        }
    });

    it('marks the page you are on, with a gold bar and stronger text as well as aria-current', () => {
        open([], '/members');
        const current = screen.getByRole('link', { current: 'page' });

        expect(current).toHaveTextContent('Members');
        expect(current).toHaveClass('font-medium', 'before:bg-gold');
        expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveAttribute('aria-current');
        expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveClass('before:bg-gold');
    });

    it('moves the mark when another link is followed', async () => {
        open([], '/members');

        await userEvent.setup().click(screen.getByRole('link', { name: 'Dashboard' }));

        expect(screen.getByRole('link', { current: 'page' })).toHaveTextContent('Dashboard');
        expect(screen.getByText('Dashboard page')).toBeInTheDocument();
    });

    it('draws the page inside a centred, padded area', () => {
        open();

        expect(screen.getByRole('main')).toHaveClass('mx-auto', 'max-w-6xl');
        expect(within(screen.getByRole('main')).getByText('Members page')).toBeInTheDocument();
    });

    it('shows the SMS credit balance and the signed-in person in the header', () => {
        open();
        const header = screen.getByRole('banner');

        expect(within(header).getByText('SMS credits')).toBeInTheDocument();
        expect(within(header).getByText('120')).toBeInTheDocument();
        expect(within(header).getByText('Akosua Mensah')).toBeInTheDocument();
        expect(within(header).getByText('AM')).toBeInTheDocument();
    });

    it('signs out, and keeps the button from being pressed twice while it works', async () => {
        vi.mocked(authApi.logout).mockImplementation(() => new Promise(() => undefined));
        open();
        const button = screen.getByRole('button', { name: 'Sign out' });

        await userEvent.setup().click(button);

        await waitFor(() => expect(button).toBeDisabled());
        expect(authApi.logout).toHaveBeenCalledOnce();
    });

    it('is not printed: neither the menu nor the header', () => {
        open();

        expect(screen.getByRole('navigation', { name: 'Main' }).closest('aside')).toHaveClass('print:hidden');
        expect(screen.getByRole('banner')).toHaveClass('print:hidden');
    });

    it('makes each link a comfortable target: 44px on a phone, 40px beside it on desktop', () => {
        open();

        expect(screen.getByRole('link', { name: 'Members' })).toHaveClass('h-11', 'md:h-10');
    });
});
