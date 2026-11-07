import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTE_PATTERNS } from '@/constants/routes';
import { MemberDetailPage } from '@/pages/MemberDetailPage';
import { ApiError } from '@/services/ApiError';
import { membersApi } from '@/services/api/members';
import { member } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/members');

const EDITOR: Permission[] = [PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.MEMBERS_EDIT];

function open(permissions: Permission[] = [], query = '') {
    return renderPage(<MemberDetailPage />, { path: ROUTE_PATTERNS.MEMBER, url: `/members/member-1${query}`, permissions });
}

describe('MemberDetailPage', () => {
    beforeEach(() => {
        vi.mocked(membersApi.get).mockResolvedValue(
            member({
                address: '12 Ring Road',
                family: { id: 'family-9', name: 'Mensah Family', role: 'head', role_label: 'Head' },
                departments: [{ id: 'd1', name: 'Choir', role: null }],
                assigned_pastor: { id: 'u1', name: 'Pastor Adjei' },
                milestones: [
                    {
                        id: 'ms1',
                        type: 'baptism',
                        type_label: 'Baptism',
                        occurred_at: '2010-06-05',
                        officiant: null,
                        cohort: null,
                        notes: null,
                    },
                ],
            }),
        );
    });

    it('shows who the member is and the details the church holds', async () => {
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Emmanuel Mensah-Boateng' })).toBeInTheDocument();
        expect(screen.getByText('GRC-0001')).toBeInTheDocument();
        expect(screen.getByText('12 Ring Road')).toBeInTheDocument();
        expect(screen.getByText('12 Jul 1985')).toBeInTheDocument();
        expect(screen.getByText('Pastor Adjei')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Mensah Family (Head)' })).toHaveAttribute('href', '/families/family-9');
        expect(screen.getByRole('listitem')).toHaveTextContent('Baptism · 5 Jun 2010');
    });

    it('leaves the milestones out for roles the API did not send them to', async () => {
        vi.mocked(membersApi.get).mockResolvedValue(member());
        open();
        await screen.findByRole('heading', { level: 1 });

        expect(screen.queryByText('Milestones')).not.toBeInTheDocument();
    });

    it('opens the tab named in the address, and changing tab updates the address', async () => {
        const user = userEvent.setup();
        const { router } = open([], '?tab=attendance');

        expect(await screen.findByText('3 services')).toBeInTheDocument();

        await user.click(screen.getByRole('tab', { name: 'Giving' }));
        expect(await screen.findByText('Your role does not include giving details.')).toBeInTheDocument();
        expect(router.state.location.search).toBe('?tab=giving');
    });

    it('shows the giving amounts to roles that receive them', async () => {
        vi.mocked(membersApi.get).mockResolvedValue(
            member({
                giving_summary: {
                    this_year: '2400.00',
                    this_year_formatted: 'GHS 2,400.00',
                    this_month: '200.00',
                    this_month_formatted: 'GHS 200.00',
                    all_time: '8650.00',
                    all_time_formatted: 'GHS 8,650.00',
                },
            }),
        );
        open([], '?tab=giving');

        expect(await screen.findByText('GHS 8,650.00')).toBeInTheDocument();
    });

    it('offers Edit, Change status and Change photo only to staff who may edit', async () => {
        open(EDITOR);

        expect(await screen.findByRole('link', { name: 'Edit' })).toHaveAttribute('href', '/members/member-1/edit');
        expect(screen.getByRole('button', { name: 'Change status' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Change photo' })).toBeInTheDocument();
    });

    it('hides those buttons from staff who may only view', async () => {
        open([PERMISSIONS.MEMBERS_VIEW]);
        await screen.findByRole('heading', { level: 1 });

        expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument();
    });

    it('keeps the record of a deceased member read-only even for editors', async () => {
        vi.mocked(membersApi.get).mockResolvedValue(member({ status: 'deceased', status_label: 'Deceased' }));
        open(EDITOR);
        await screen.findByRole('heading', { level: 1 });

        expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Change photo' })).not.toBeInTheDocument();
    });

    it('says plainly when the member cannot be found', async () => {
        vi.mocked(membersApi.get).mockRejectedValue(new ApiError('Not found', 404));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('not found, or you do not have access');
        expect(screen.getByRole('link', { name: 'Back to members' })).toBeInTheDocument();
    });

    it('offers to try again when the server cannot be reached', async () => {
        vi.mocked(membersApi.get).mockRejectedValueOnce(new ApiError('Cannot reach the server.', 0));
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Try again' }));

        expect(await screen.findByRole('heading', { level: 1, name: 'Emmanuel Mensah-Boateng' })).toBeInTheDocument();
    });

    describe('changing status', () => {
        beforeEach(() => {
            vi.mocked(membersApi.changeStatus).mockResolvedValue(member({ status: 'inactive', status_label: 'Inactive' }));
        });

        it('moves a member to a status that stays in active life without asking twice', async () => {
            vi.mocked(membersApi.get).mockResolvedValue(member({ status: 'prospect', status_label: 'Prospect' }));
            const user = userEvent.setup();
            open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Change status' }));
            await user.selectOptions(screen.getByLabelText(/New status/), 'active');
            await user.type(screen.getByLabelText('Reason (optional)'), 'Received into membership');
            await user.click(screen.getByRole('button', { name: 'Save' }));

            await waitFor(() => expect(membersApi.changeStatus).toHaveBeenCalledWith('member-1', 'active', 'Received into membership'));
        });

        it('asks "Are you sure?" before moving someone out of active life, and then does it', async () => {
            const user = userEvent.setup();
            open([...EDITOR, PERMISSIONS.MEMBERS_DEACTIVATE]);

            await user.click(await screen.findByRole('button', { name: 'Change status' }));
            await user.selectOptions(screen.getByLabelText(/New status/), 'inactive');
            await user.click(screen.getByRole('button', { name: 'Save' }));

            const confirm = await screen.findByRole('alertdialog', { name: 'Move Emmanuel Mensah-Boateng to Inactive?' });
            expect(membersApi.changeStatus).not.toHaveBeenCalled();
            await user.click(within(confirm).getByRole('button', { name: 'Move to Inactive' }));

            await waitFor(() => expect(membersApi.changeStatus).toHaveBeenCalledWith('member-1', 'inactive', null));
        });

        it('does not do it when the user says no', async () => {
            const user = userEvent.setup();
            open([...EDITOR, PERMISSIONS.MEMBERS_DEACTIVATE]);

            await user.click(await screen.findByRole('button', { name: 'Change status' }));
            await user.selectOptions(screen.getByLabelText(/New status/), 'archived');
            await user.click(screen.getByRole('button', { name: 'Save' }));
            await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Cancel' }));

            expect(membersApi.changeStatus).not.toHaveBeenCalled();
        });

        it('does not offer statuses that take a member out of active life without the deactivate permission', async () => {
            const user = userEvent.setup();
            open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Change status' }));
            const options = within(screen.getByLabelText(/New status/))
                .getAllByRole('option')
                .map((option) => option.textContent);

            expect(options).toEqual(['Choose a status', 'Prospect']);
        });

        it('asks for a status before saving', async () => {
            const user = userEvent.setup();
            open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Change status' }));
            await user.click(screen.getByRole('button', { name: 'Save' }));

            expect(await screen.findByRole('alert')).toHaveTextContent('Choose a status');
            expect(membersApi.changeStatus).not.toHaveBeenCalled();
        });
    });

    describe('photo', () => {
        function pickFile(file: File) {
            fireEvent.change(screen.getByLabelText('Member photo file'), { target: { files: [file] } });
        }

        it('uploads a JPG', async () => {
            vi.mocked(membersApi.uploadPhoto).mockResolvedValue('https://signed.example/photo.jpg');
            open(EDITOR);
            await screen.findByRole('button', { name: 'Change photo' });

            pickFile(new File(['x'], 'me.jpg', { type: 'image/jpeg' }));

            await waitFor(() => expect(membersApi.uploadPhoto).toHaveBeenCalledWith('member-1', expect.any(File)));
        });

        it('refuses a file that is not a JPG or PNG, without calling the server', async () => {
            open(EDITOR);
            await screen.findByRole('button', { name: 'Change photo' });

            pickFile(new File(['x'], 'cv.pdf', { type: 'application/pdf' }));

            expect(membersApi.uploadPhoto).not.toHaveBeenCalled();
        });

        it('refuses a photo over 5 MB, without calling the server', async () => {
            open(EDITOR);
            await screen.findByRole('button', { name: 'Change photo' });
            const big = new File(['x'], 'big.jpg', { type: 'image/jpeg' });
            Object.defineProperty(big, 'size', { value: 6 * 1024 * 1024 });

            pickFile(big);

            expect(membersApi.uploadPhoto).not.toHaveBeenCalled();
        });
    });

    describe('milestones', () => {
        it('adds a milestone', async () => {
            vi.mocked(membersApi.addMilestone).mockResolvedValue({
                id: 'ms2',
                type: 'confirmation',
                type_label: 'Confirmation',
                occurred_at: '2024-05-05',
                officiant: 'Rev. Mensah',
                cohort: null,
                notes: null,
            });
            const user = userEvent.setup();
            open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Add milestone' }));
            await user.selectOptions(screen.getByLabelText(/^Milestone/), 'confirmation');
            await user.type(screen.getByLabelText(/^Date/), '2024-05-05');
            await user.type(screen.getByLabelText('Officiant (optional)'), 'Rev. Mensah');
            await user.click(screen.getByRole('button', { name: 'Add milestone', description: '' }));

            await waitFor(() =>
                expect(membersApi.addMilestone).toHaveBeenCalledWith('member-1', {
                    type: 'confirmation',
                    occurred_at: '2024-05-05',
                    officiant: 'Rev. Mensah',
                    cohort: null,
                    notes: null,
                }),
            );
        });

        it("shows the server's message next to the field it is about", async () => {
            vi.mocked(membersApi.addMilestone).mockRejectedValue(
                new ApiError('Invalid', 422, { occurred_at: ['The date cannot be in the future.'] }),
            );
            const user = userEvent.setup();
            open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Add milestone' }));
            await user.selectOptions(screen.getByLabelText(/^Milestone/), 'baptism');
            await user.type(screen.getByLabelText(/^Date/), '2024-05-05');
            await user.click(screen.getAllByRole('button', { name: 'Add milestone' }).at(-1) as HTMLElement);

            expect(await screen.findByText('The date cannot be in the future.')).toBeInTheDocument();
        });

        it('does not offer Add milestone to staff who may only view', async () => {
            open([PERMISSIONS.MEMBERS_VIEW]);
            await screen.findByText('Milestones');

            expect(screen.queryByRole('button', { name: 'Add milestone' })).not.toBeInTheDocument();
        });
    });
});
