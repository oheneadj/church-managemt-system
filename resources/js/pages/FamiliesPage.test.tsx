import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { FamiliesPage } from '@/pages/FamiliesPage';
import { ApiError } from '@/services/ApiError';
import { familiesApi } from '@/services/api/families';
import { family, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/families');

function open(url: string = ROUTES.FAMILIES, permissions: Permission[] = []) {
    return renderPage(<FamiliesPage />, { path: ROUTES.FAMILIES, url, permissions });
}

describe('FamiliesPage', () => {
    beforeEach(() => {
        vi.mocked(familiesApi.list).mockResolvedValue(
            pageOf([family({ members_count: 3 }), family({ id: 'family-2', name: 'Asante Family', members_count: 1 })]),
        );
    });

    it('lists the families with their member counts', async () => {
        open();

        expect(await screen.findByRole('link', { name: /Mensah Family/ })).toHaveTextContent('3 members');
        expect(screen.getByRole('link', { name: /Asante Family/ })).toHaveTextContent('1 member');
        expect(screen.getByText('2 found')).toBeInTheDocument();
        expect(familiesApi.list).toHaveBeenCalledWith({ search: '', page: 1 });
    });

    it('searches by name and keeps the search in the address', async () => {
        const user = userEvent.setup();
        const { router } = open();
        await screen.findByRole('link', { name: /Mensah/ });

        await user.type(screen.getByLabelText('Search families'), 'asante');
        await user.click(screen.getByRole('button', { name: 'Search' }));

        await waitFor(() => expect(familiesApi.list).toHaveBeenLastCalledWith({ search: 'asante', page: 1 }));
        expect(router.state.location.search).toBe('?search=asante');
    });

    it('opens on the page named in the address and moves to the next', async () => {
        vi.mocked(familiesApi.list).mockResolvedValue(pageOf([family()], { current_page: 2, last_page: 3, total: 60 }));
        const user = userEvent.setup();
        const { router } = open('/families?page=2');
        await screen.findByText('Page 2 of 3');

        await user.click(screen.getByRole('button', { name: 'Next' }));

        await waitFor(() => expect(router.state.location.search).toBe('?page=3'));
    });

    it('says plainly when there are no families', async () => {
        vi.mocked(familiesApi.list).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByRole('heading', { name: 'No families found' })).toBeInTheDocument();
    });

    it('shows the error and lets the user try again', async () => {
        vi.mocked(familiesApi.list).mockRejectedValueOnce(new ApiError('Cannot reach the server.', 0));
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Try again' }));

        expect(await screen.findByRole('link', { name: /Mensah Family/ })).toBeInTheDocument();
    });

    it('lets staff who may edit create a family, then opens it', async () => {
        vi.mocked(familiesApi.create).mockResolvedValue(family({ id: 'family-9', name: 'Boateng Family' }));
        const user = userEvent.setup();
        const { router } = open(ROUTES.FAMILIES, [PERMISSIONS.MEMBERS_EDIT]);

        await user.click(await screen.findByRole('button', { name: 'New family' }));
        await user.type(screen.getByLabelText(/Family name/), 'Boateng Family');
        await user.click(screen.getByRole('button', { name: 'Create family' }));

        await waitFor(() => expect(familiesApi.create).toHaveBeenCalledWith({ name: 'Boateng Family', notes: null }));
        await waitFor(() => expect(router.state.location.pathname).toBe('/families/family-9'));
    });

    it('asks for a name before creating', async () => {
        const user = userEvent.setup();
        open(ROUTES.FAMILIES, [PERMISSIONS.MEMBERS_EDIT]);

        await user.click(await screen.findByRole('button', { name: 'New family' }));
        await user.click(screen.getByRole('button', { name: 'Create family' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Enter the family name');
        expect(familiesApi.create).not.toHaveBeenCalled();
    });

    it('hides New family from staff who may only view', async () => {
        open(ROUTES.FAMILIES, [PERMISSIONS.MEMBERS_VIEW]);
        await screen.findByRole('link', { name: /Mensah/ });

        expect(screen.queryByRole('button', { name: 'New family' })).not.toBeInTheDocument();
    });
});
