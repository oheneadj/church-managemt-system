import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * Placeholder home screen. The real role-based summary cards arrive in Sprint 5; for now it
 * proves the sign-in, church context and guard work end to end.
 */
export function DashboardPage() {
    const user = useAuthStore((state) => state.user);
    const church = useChurchStore((state) => state.church);

    return (
        <section aria-labelledby="dashboard-heading">
            <h1 id="dashboard-heading" className="text-xl font-semibold">
                Welcome, {user?.name}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">You are signed in to {church?.name}.</p>
        </section>
    );
}
