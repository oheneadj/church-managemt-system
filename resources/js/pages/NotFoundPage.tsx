import { Link } from 'react-router';
import { ROUTES } from '@/constants/routes';

export function NotFoundPage() {
    return (
        <main className="flex min-h-svh flex-col items-center justify-center gap-2 p-4 text-center">
            <h1 className="text-xl font-semibold">Page not found</h1>
            <p className="text-sm text-muted-foreground">The page you are looking for does not exist.</p>
            <Link to={ROUTES.HOME} className="text-sm underline underline-offset-4">
                Go to the home page
            </Link>
        </main>
    );
}
