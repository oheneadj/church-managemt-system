import { Navigate } from 'react-router';
import { ROUTES } from '@/constants/routes';
import { isPlatformHost } from '@/lib/platformHost';

/**
 * Sends the home address to the right first page: the sign-up page on the platform's own address,
 * and the dashboard (behind sign-in) on a church's address.
 */
export function HomeRedirect() {
    return <Navigate to={isPlatformHost() ? ROUTES.START : ROUTES.DASHBOARD} replace />;
}
