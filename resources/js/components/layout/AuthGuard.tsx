import { Navigate, Outlet } from 'react-router';
import { ROUTES } from '@/constants/routes';
import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Layout route wrapping every private page: signed-out visitors are sent to the login screen.
 */
export function AuthGuard() {
    const user = useAuthStore((state) => state.user);

    if (!user) {
        return <Navigate to={ROUTES.LOGIN} replace />;
    }

    return <Outlet />;
}
