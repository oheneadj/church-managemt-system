import { useEffect } from 'react';
import { Link, useRouteError } from 'react-router';
import { ROUTES } from '@/constants/routes';
import { reportError } from '@/lib/errorMonitoring';

/**
 * What the user sees when a screen crashes. It replaces React Router's raw error page, says what to do in plain
 * words, and reports the error to the monitor. The error text itself is never shown (it can name files and code).
 */
export function RouteErrorPage() {
    const error = useRouteError();

    useEffect(() => {
        reportError(error);
    }, [error]);

    return (
        <main className="flex min-h-svh flex-col items-center justify-center gap-3 p-4 text-center">
            <h1 className="text-xl font-semibold">Something went wrong</h1>
            <p className="max-w-md text-sm text-muted-foreground">
                We have been told about it. Please try again, and if it keeps happening, contact your church admin.
            </p>
            <div className="flex gap-3">
                <button type="button" className="text-sm underline underline-offset-4" onClick={() => window.location.reload()}>
                    Reload this page
                </button>
                <Link to={ROUTES.HOME} className="text-sm underline underline-offset-4">
                    Go to the home page
                </Link>
            </div>
        </main>
    );
}
