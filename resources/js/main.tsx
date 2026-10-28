import { QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router/dom';
import '../css/app.css';
import { PageSpinner } from '@/components/layout/PageSpinner';
import { Toaster } from '@/components/ui/sonner';
import { queryClient } from '@/lib/queryClient';
import { restoreSession } from '@/lib/restoreSession';
import { router } from '@/router';

const container = document.getElementById('root');

if (container === null) {
    throw new Error('The #root element is missing from the page shell.');
}

const root = createRoot(container);

// Check for an existing session before the first screen, so a refresh never flashes the login page.
root.render(<PageSpinner />);

void restoreSession().then(() => {
    root.render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <RouterProvider router={router} />
                <Toaster />
            </QueryClientProvider>
        </StrictMode>,
    );
});
