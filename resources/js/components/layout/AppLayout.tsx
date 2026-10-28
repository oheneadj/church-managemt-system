import { NavLink, Outlet } from 'react-router';
import { Button } from '@/components/ui/button';
import { ROUTES } from '@/constants/routes';
import { useSignOut } from '@/hooks/useSignOut';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

const NAV_ITEMS = [
    { label: 'Dashboard', to: ROUTES.DASHBOARD },
    { label: 'Members', to: ROUTES.MEMBERS },
] as const;

/**
 * The signed-in shell: a sidebar for navigation and a header with the church and the sign-out button.
 * It collapses to a top bar on small screens, because most staff use phones.
 */
export function AppLayout() {
    const churchName = useChurchStore((state) => state.church?.name);
    const userName = useAuthStore((state) => state.user?.name);
    const signOut = useSignOut();

    return (
        <div className="flex min-h-svh flex-col md:flex-row">
            <aside className="border-b bg-sidebar text-sidebar-foreground md:w-60 md:border-r md:border-b-0">
                <div className="px-4 py-4 font-semibold">{churchName}</div>
                <nav aria-label="Main" className="flex gap-1 px-2 pb-2 md:flex-col">
                    {NAV_ITEMS.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            className={({ isActive }) =>
                                `rounded-md px-3 py-2 text-sm ${isActive ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/60'}`
                            }
                        >
                            {item.label}
                        </NavLink>
                    ))}
                </nav>
            </aside>
            <div className="flex flex-1 flex-col">
                <header className="flex items-center justify-between border-b px-4 py-3">
                    <span className="text-sm text-muted-foreground">{userName}</span>
                    <Button variant="outline" size="sm" disabled={signOut.isPending} onClick={() => signOut.mutate()}>
                        Sign out
                    </Button>
                </header>
                <main className="flex-1 p-4">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
