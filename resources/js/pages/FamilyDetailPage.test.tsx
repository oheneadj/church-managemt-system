import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTE_PATTERNS } from '@/constants/routes';
import { FamilyDetailPage } from '@/pages/FamilyDetailPage';
import { ApiError } from '@/services/ApiError';
import { familiesApi } from '@/services/api/families';
import { family } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/families');

function open(permissions: Permission[] = []) {
    return renderPage(<FamilyDetailPage />, { path: ROUTE_PATTERNS.FAMILY, url: '/families/family-1', permissions });
}

describe('FamilyDetailPage', () => {
    beforeEach(() => {
        vi.mocked(familiesApi.get).mockResolvedValue(
            family({
                notes: 'Moved from Kumasi in 2022',
                members: [
                    {
                        id: 'm1',
                        member_number: 'GRC-0001',
                        full_name: 'Kofi Mensah',
                        gender: 'male',
                        gender_label: 'Male',
                        status: 'active',
                        status_label: 'Active',
                        family_role: 'head',
                        family_role_label: 'Head',
                    },
                ],
            }),
        );
    });

    it('shows the family, its notes and its members', async () => {
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Mensah Family' })).toBeInTheDocument();
        expect(screen.getByText('Moved from Kumasi in 2022')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Kofi Mensah/ })).toHaveAttribute('href', '/members/m1');
    });

    it('explains how to add members when none can be shown', async () => {
        vi.mocked(familiesApi.get).mockResolvedValue(family({ members: [] }));
        open();

        expect(await screen.findByRole('heading', { name: 'No members to show' })).toBeInTheDocument();
    });

    it('lets staff who may edit rename the family', async () => {
        vi.mocked(familiesApi.update).mockResolvedValue(family({ name: 'Mensah-Boateng Family' }));
        const user = userEvent.setup();
        open([PERMISSIONS.MEMBERS_EDIT]);

        await user.click(await screen.findByRole('button', { name: 'Edit' }));
        const name = screen.getByLabelText(/Family name/);
        await user.clear(name);
        await user.type(name, 'Mensah-Boateng Family');
        await user.click(screen.getByRole('button', { name: 'Save' }));

        await waitFor(() =>
            expect(familiesApi.update).toHaveBeenCalledWith('family-1', {
                name: 'Mensah-Boateng Family',
                notes: 'Moved from Kumasi in 2022',
            }),
        );
    });

    it('hides Edit from staff who may only view', async () => {
        open([PERMISSIONS.MEMBERS_VIEW]);
        await screen.findByRole('heading', { level: 1 });

        expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
    });

    it('says plainly when the family cannot be found', async () => {
        vi.mocked(familiesApi.get).mockRejectedValue(new ApiError('Not found', 404));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('not found, or you do not have access');
        expect(screen.getByRole('link', { name: 'Back to families' })).toBeInTheDocument();
    });
});
