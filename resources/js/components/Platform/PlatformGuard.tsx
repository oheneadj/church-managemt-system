import { Navigate } from 'react-router';
import { PageSpinner } from '@/components/layout/PageSpinner';
import { PlatformLayout } from '@/components/Platform/PlatformLayout';
import { ROUTES } from '@/constants/routes';
import { usePlatformSession } from '@/hooks/usePlatform';

/**
 * Wraps every private platform screen: asks the server who is signed in, shows the platform shell around the screen, and sends
 * anyone who is not signed in to the platform sign-in.
 */
export function PlatformGuard() {
    const session = usePlatformSession();

    if (session.isPending) {
        return <PageSpinner />;
    }

    if (session.isError) {
        return <Navigate to={ROUTES.PLATFORM_LOGIN} replace />;
    }

    return <PlatformLayout admin={session.data} />;
}
