import { NavLink, Outlet, useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import { ROUTES } from '@/constants/routes';
import { usePlatformSignOut } from '@/hooks/usePlatform';
import type { PlatformAdmin } from '@/types/platform';

/**
 * The platform owner's shell: a header with who is signed in and a sign-out button, a menu of the platform's own screens, and
 * the screen. It shows nothing of any church.
 */
export function PlatformLayout({ admin }: { admin: PlatformAdmin }) {
    const signOut = usePlatformSignOut();
    const navigate = useNavigate();

    return (
        <div className="flex min-h-svh flex-col">
            <header className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b px-4 py-3">
                <span className="font-semibold">Platform admin</span>
                <nav aria-label="Platform" className="flex gap-1">
                    <NavLink
                        to={ROUTES.PLATFORM_PACKAGES}
                        className={({ isActive }) =>
                            `rounded-md px-3 py-2 text-sm ${isActive ? 'bg-muted font-medium' : 'hover:bg-muted/60'}`
                        }
                    >
                        SMS packages
                    </NavLink>
                </nav>
                <span className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">{admin.name}</span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={signOut.isPending}
                        onClick={() =>
                            signOut.mutate(undefined, { onSuccess: () => void navigate(ROUTES.PLATFORM_LOGIN, { replace: true }) })
                        }
                    >
                        Sign out
                    </Button>
                </span>
            </header>
            <main className="flex-1 p-4">
                <Outlet />
            </main>
        </div>
    );
}
