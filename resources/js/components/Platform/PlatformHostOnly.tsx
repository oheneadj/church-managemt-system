import { Outlet } from 'react-router';
import { isPlatformHost } from '@/lib/platformHost';
import { NotFoundPage } from '@/pages/NotFoundPage';

/**
 * Wraps the platform owner's screens (D-069): they exist only on the platform host. On a church's address they are not found,
 * as the server also answers 404 there, so a church's staff never see an admin screen.
 */
export function PlatformHostOnly() {
    return isPlatformHost() ? <Outlet /> : <NotFoundPage />;
}
