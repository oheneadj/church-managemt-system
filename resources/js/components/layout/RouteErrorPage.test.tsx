import { render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RouteErrorPage } from '@/components/layout/RouteErrorPage';
import { reportError } from '@/lib/errorMonitoring';

vi.mock('@/lib/errorMonitoring');

function Crashes(): never {
    throw new Error('secret internal detail');
}

describe('RouteErrorPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
    });

    it('shows a plain message instead of the error, reports it, and offers a way out', async () => {
        const router = createMemoryRouter([{ path: '/', element: <Crashes />, errorElement: <RouteErrorPage /> }]);
        render(<RouterProvider router={router} />);

        expect(await screen.findByRole('heading', { level: 1, name: 'Something went wrong' })).toBeInTheDocument();
        expect(screen.queryByText(/secret internal detail/)).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Reload this page' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Go to the home page' })).toBeInTheDocument();
        expect(reportError).toHaveBeenCalledWith(expect.objectContaining({ message: 'secret internal detail' }));
    });
});
