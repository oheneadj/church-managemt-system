import {
    CalendarDaysIcon,
    ChartColumnIcon,
    HandshakeIcon,
    HeartHandshakeIcon,
    HouseIcon,
    LayoutDashboardIcon,
    MessageSquareIcon,
    SettingsIcon,
    UserCogIcon,
    UsersIcon,
    type LucideIcon,
} from 'lucide-react';
import { NavLink, Outlet } from 'react-router';
import { SmsCreditBalance } from '@/components/layout/SmsCreditBalance';
import { Button } from '@/components/ui/button';
import { ProfileImage } from '@/components/ui/ProfileImage';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useHasPermission } from '@/hooks/useCan';
import { useSignOut } from '@/hooks/useSignOut';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';
import { initialsOf } from '@/utils/initialsOf';
import { cn } from 'cn';

interface NavEntry {
    label: string;
    to: string;
    icon: LucideIcon;
    permission?: Permission;
}

/** The menu. An item with a permission is shown only to staff who hold it (the server still enforces it). */
const NAV_ITEMS: readonly NavEntry[] = [
    { label: 'Dashboard', to: ROUTES.DASHBOARD, icon: LayoutDashboardIcon },
    { label: 'Members', to: ROUTES.MEMBERS, icon: UsersIcon },
    { label: 'Families', to: ROUTES.FAMILIES, icon: HouseIcon },
    { label: 'Services', to: ROUTES.SERVICES, icon: CalendarDaysIcon },
    { label: 'Giving', to: ROUTES.GIVING, icon: HeartHandshakeIcon, permission: PERMISSIONS.GIVING_VIEW },
    { label: 'Pledges', to: ROUTES.PLEDGES, icon: HandshakeIcon, permission: PERMISSIONS.GIVING_VIEW },
    { label: 'Reports', to: ROUTES.REPORTS, icon: ChartColumnIcon, permission: PERMISSIONS.REPORTS_VIEW },
    { label: 'Messages', to: ROUTES.MESSAGES, icon: MessageSquareIcon, permission: PERMISSIONS.SMS_SEND },
    { label: 'Staff', to: ROUTES.STAFF, icon: UserCogIcon, permission: PERMISSIONS.STAFF_MANAGE },
    { label: 'Settings', to: ROUTES.SETTINGS, icon: SettingsIcon, permission: PERMISSIONS.CHURCH_SETTINGS },
];

/**
 * One menu link, hidden from staff who lack its permission. The page you are on is marked three ways: a soft fill, stronger text
 * and a fine gold bar (and `aria-current` for screen readers), so it never depends on colour alone.
 */
function NavItem({ label, to, icon: Icon, permission }: NavEntry) {
    const allowed = useHasPermission(permission);

    return allowed ? (
        <NavLink
            to={to}
            className={({ isActive }) =>
                cn(
                    'relative flex h-11 items-center gap-3 rounded-lg px-3 text-body-sm outline-none focus-visible:ring-3 focus-visible:ring-ring md:h-10',
                    isActive
                        ? 'bg-accent font-medium text-accent-foreground before:absolute before:inset-y-2 before:left-0 before:w-0.5 before:rounded-full before:bg-gold'
                        : 'text-muted-foreground hover:bg-surface-hover hover:text-foreground',
                )
            }
        >
            <Icon aria-hidden="true" className="size-5 shrink-0" />
            {label}
        </NavLink>
    ) : null;
}

/** The church's mark: its initials on the deep brand colour, beside its name. */
function ChurchBrand({ name }: { name: string }) {
    return (
        <div className="flex items-center gap-3 px-4 py-4">
            <span
                aria-hidden="true"
                className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary text-body-sm font-semibold text-primary-foreground"
            >
                {initialsOf(name)}
            </span>
            <span className="min-w-0 truncate font-semibold">{name}</span>
        </div>
    );
}

/**
 * The signed-in shell (docs/design-system): a sidebar with the church's mark and an icon menu, a slim header with the SMS credit
 * balance and the signed-in person, and a centred, padded page area. On a phone the sidebar becomes a top band and the menu links
 * wrap onto rows, all visible, because most staff use phones. Neither is printed, so a printed report is the report alone.
 */
export function AppLayout() {
    const churchName = useChurchStore((state) => state.church?.name) ?? '';
    const userName = useAuthStore((state) => state.user?.name) ?? '';
    const signOut = useSignOut();

    return (
        <div className="flex min-h-svh flex-col bg-background md:flex-row">
            <aside className="border-b bg-sidebar text-sidebar-foreground print:hidden md:sticky md:top-0 md:flex md:h-svh md:w-64 md:shrink-0 md:flex-col md:border-r md:border-b-0">
                <ChurchBrand name={churchName} />
                <nav
                    aria-label="Main"
                    className="flex flex-wrap gap-1 px-3 pb-3 md:flex-1 md:flex-col md:flex-nowrap md:overflow-y-auto md:py-1"
                >
                    {NAV_ITEMS.map((item) => (
                        <NavItem key={item.to} {...item} />
                    ))}
                </nav>
            </aside>
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b bg-card px-4 py-3 print:hidden md:px-8">
                    <SmsCreditBalance />
                    <div className="flex items-center gap-3">
                        <ProfileImage name={userName} size="sm" decorative />
                        <span className="sr-only text-body-sm sm:not-sr-only">{userName}</span>
                        <Button variant="outline" size="sm" disabled={signOut.isPending} onClick={() => signOut.mutate()}>
                            Sign out
                        </Button>
                    </div>
                </header>
                <main className="mx-auto w-full max-w-6xl flex-1 p-4 md:p-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
