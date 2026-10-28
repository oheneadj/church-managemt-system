import { Navigate, Outlet } from 'react-router';
import { ROUTES } from '@/constants/routes';
import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Layout route wrapping every private page: signed-out visitors are sent to the login screen.
 */
export function AuthGuard() {
    const token = useAuthStore((state) => state.token);

    if (!token) {
        return <Navigate to={ROUTES.LOGIN} replace />;
    }

    return <Outlet />;
}
