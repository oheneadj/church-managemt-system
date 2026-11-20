import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MemberPrivacyActions } from '@/components/Members/MemberPrivacyActions';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { ApiError } from '@/services/ApiError';
import { membersApi } from '@/services/api/members';
import { renderPage } from '@/test/render';
import { downloadJson } from '@/utils/downloadJson';

vi.mock('@/services/api/members');
vi.mock('@/utils/downloadJson');

function open(permissions: Permission[]) {
    return renderPage(<MemberPrivacyActions memberId="member-1" memberNumber="GRC-0001" fullName="Abena Asante" />, {
        path: '/members/:id',
        url: '/members/member-1',
        permissions,
    });
}

describe('MemberPrivacyActions', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows nothing to staff who may neither export nor erase', () => {
        open([PERMISSIONS.MEMBERS_EDIT]);

        expect(screen.queryByText('Personal data')).not.toBeInTheDocument();
    });

    it('shows only the buttons the user holds the permission for', () => {
        open([PERMISSIONS.MEMBERS_EXPORT]);

        expect(screen.getByRole('button', { name: 'Download their data' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Erase personal data' })).not.toBeInTheDocument();
    });

    it('downloads the exported data as a file named after the member number', async () => {
        vi.mocked(membersApi.exportData).mockResolvedValue({ member: { first_name: 'Abena' } });
        open([PERMISSIONS.MEMBERS_EXPORT]);

        await userEvent.click(screen.getByRole('button', { name: 'Download their data' }));

        await waitFor(() => expect(downloadJson).toHaveBeenCalledWith('GRC-0001-data.json', { member: { first_name: 'Abena' } }));
    });

    it('asks for a reason before erasing and sends it', async () => {
        vi.mocked(membersApi.erase).mockResolvedValue(undefined);
        const { router } = open([PERMISSIONS.MEMBERS_ERASE]);

        await userEvent.click(screen.getByRole('button', { name: 'Erase personal data' }));
        await userEvent.click(screen.getAllByRole('button', { name: 'Erase personal data' }).at(-1)!);

        expect(await screen.findByText('Give a short reason')).toBeInTheDocument();
        expect(membersApi.erase).not.toHaveBeenCalled();

        await userEvent.type(screen.getByLabelText(/Reason/), 'Member asked in writing');
        await userEvent.click(screen.getAllByRole('button', { name: 'Erase personal data' }).at(-1)!);

        await waitFor(() => expect(membersApi.erase).toHaveBeenCalledWith('member-1', 'Member asked in writing'));
        await waitFor(() => expect(router.state.location.pathname).toBe(ROUTES.MEMBERS));
    });

    it('shows the server message beside the reason box', async () => {
        vi.mocked(membersApi.erase).mockRejectedValue(new ApiError('bad', 422, { reason: ['The reason is too short.'] }));
        open([PERMISSIONS.MEMBERS_ERASE]);

        await userEvent.click(screen.getByRole('button', { name: 'Erase personal data' }));
        await userEvent.type(screen.getByLabelText(/Reason/), 'Because');
        await userEvent.click(screen.getAllByRole('button', { name: 'Erase personal data' }).at(-1)!);

        expect(await screen.findByText('The reason is too short.')).toBeInTheDocument();
    });
});
