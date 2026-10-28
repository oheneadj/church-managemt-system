import { QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router/dom';
import '../css/app.css';
import { queryClient } from '@/lib/queryClient';
import { router } from '@/router';

const container = document.getElementById('root');

if (container === null) {
    throw new Error('The #root element is missing from the page shell.');
}

createRoot(container).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>
    </StrictMode>,
);
