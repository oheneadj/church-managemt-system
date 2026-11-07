import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { AbsentMembersPage } from '@/pages/AbsentMembersPage';
import { ApiError } from '@/services/ApiError';
import { attendanceApi } from '@/services/api/attendance';
import { churchApi } from '@/services/api/church';
import { absentMember, churchSettings, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/attendance');
vi.mock('@/services/api/church');

function open(url: string = ROUTES.ABSENT_MEMBERS) {
    return renderPage(<AbsentMembersPage />, { path: ROUTES.ABSENT_MEMBERS, url, permissions: [PERMISSIONS.REPORTS_VIEW] });
}

describe('AbsentMembersPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(churchApi.settings).mockResolvedValue(churchSettings({ absence_threshold: 3 }));
        vi.mocked(attendanceApi.absent).mockResolvedValue(
            pageOf([
                absentMember(),
                absentMember({
                    id: 'member-8',
                    full_name: 'Esi Owusu',
                    member_number: 'GRC-0008',
                    phone: '+233200000008',
                    last_attended_at: null,
                }),
            ]),
        );
    });

    it('lists each member with a link to their profile, a phone link, and when they were last at church', async () => {
        open();

        const first = (await screen.findByRole('link', { name: 'Kofi Boateng' })).closest('li') as HTMLElement;
        expect(screen.getByRole('link', { name: 'Kofi Boateng' })).toHaveAttribute('href', '/members/member-7');
        expect(within(first).getByRole('link', { name: '+233244111222' })).toHaveAttribute('href', 'tel:+233244111222');
        expect(within(first).getByText('GRC-0007', { exact: false })).toBeInTheDocument();
        expect(within(first).getByText('Last at church 11 Oct 2026')).toBeInTheDocument();
        expect(screen.getByText('2 found')).toBeInTheDocument();
    });

    it('says so for a member who has never been checked in', async () => {
        open();

        const second = (await screen.findByRole('link', { name: 'Esi Owusu' })).closest('li') as HTMLElement;
        expect(within(second).getByText('Not checked in yet')).toBeInTheDocument();
    });

    it('uses the church setting when no number is chosen, and names it in the first choice', async () => {
        open();
        await screen.findByRole('link', { name: 'Kofi Boateng' });

        expect(attendanceApi.absent).toHaveBeenCalledWith({ consecutive: null, page: 1 });
        expect(await screen.findByRole('option', { name: 'As set in Settings (3 services in a row)' })).toBeInTheDocument();
    });

    it('asks again for another number of services and keeps it in the address, returning to page 1', async () => {
        const user = userEvent.setup();
        const { router } = open('/attendance/absent?page=2');
        await screen.findByRole('link', { name: 'Kofi Boateng' });

        await user.selectOptions(screen.getByLabelText('Missed'), '5');

        await waitFor(() => expect(attendanceApi.absent).toHaveBeenLastCalledWith({ consecutive: 5, page: 1 }));
        expect(router.state.location.search).toBe('?consecutive=5');
    });

    it('offers 2 to 12 services in a row, the range the server accepts', async () => {
        open();
        await screen.findByRole('link', { name: 'Kofi Boateng' });

        const values = within(screen.getByLabelText('Missed'))
            .getAllByRole('option')
            .map((option) => (option as HTMLOptionElement).value);
        expect(values).toEqual(['', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']);
    });

    it('ignores a number in the address that is out of range', async () => {
        open('/attendance/absent?consecutive=99');

        await screen.findByRole('link', { name: 'Kofi Boateng' });
        expect(attendanceApi.absent).toHaveBeenCalledWith({ consecutive: null, page: 1 });
    });

    it('says plainly when nobody has gone absent, and why the list can be empty early on', async () => {
        vi.mocked(attendanceApi.absent).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('Nobody has gone absent')).toBeInTheDocument();
        expect(screen.getByText(/Until that many Sunday services have been recorded/)).toBeInTheDocument();
    });

    it('says what went wrong and tries again', async () => {
        vi.mocked(attendanceApi.absent)
            .mockRejectedValueOnce(new ApiError('The server is busy.', 500))
            .mockResolvedValue(pageOf([absentMember()]));
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByRole('link', { name: 'Kofi Boateng' })).toBeInTheDocument();
    });
});
