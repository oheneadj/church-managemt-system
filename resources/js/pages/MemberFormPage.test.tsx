import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTE_PATTERNS, ROUTES } from '@/constants/routes';
import { MemberFormPage } from '@/pages/MemberFormPage';
import { ApiError } from '@/services/ApiError';
import { departmentsApi } from '@/services/api/departments';
import { familiesApi } from '@/services/api/families';
import { membersApi } from '@/services/api/members';
import { family, member, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/members');
vi.mock('@/services/api/departments');
vi.mock('@/services/api/families');

const EDITOR: Permission[] = [PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.MEMBERS_EDIT];

function openNew(url: string = ROUTES.MEMBER_NEW, permissions: Permission[] = EDITOR) {
    return renderPage(<MemberFormPage />, { path: ROUTES.MEMBER_NEW, url, permissions });
}

function openEdit(permissions: Permission[] = EDITOR) {
    return renderPage(<MemberFormPage />, { path: ROUTE_PATTERNS.MEMBER_EDIT, url: ROUTES.MEMBER_EDIT('member-1'), permissions });
}

/** Fills step 1 and moves to step 2. */
async function completeStepOne(user: ReturnType<typeof userEvent.setup>) {
    await user.type(await screen.findByLabelText(/First name/), 'Abena');
    await user.type(screen.getByLabelText(/Last name/), 'Asante');
    await user.selectOptions(screen.getByLabelText(/Gender/), 'female');
    await user.click(screen.getByRole('button', { name: 'Next' }));
}

describe('MemberFormPage', () => {
    beforeEach(() => {
        vi.mocked(departmentsApi.list).mockResolvedValue(
            pageOf([{ id: 'dept-1', name: 'Choir', description: null, head_user_id: null, is_active: true }]),
        );
        vi.mocked(familiesApi.listAll).mockResolvedValue([family()]);
        vi.mocked(membersApi.create).mockResolvedValue(member({ id: 'new-member' }));
        vi.mocked(membersApi.get).mockResolvedValue(
            member({ occupation: 'Teacher', departments: [{ id: 'dept-1', name: 'Choir', role: null }] }),
        );
        vi.mocked(membersApi.update).mockResolvedValue(member());
    });

    describe('adding a member', () => {
        it('takes a member through three steps and then opens their profile', async () => {
            const user = userEvent.setup();
            const { router } = openNew();

            expect(await screen.findByText('Step 1 of 3')).toBeInTheDocument();
            await completeStepOne(user);

            expect(await screen.findByText('Step 2 of 3')).toBeInTheDocument();
            await user.type(screen.getByLabelText(/^Phone number/), '0200987654');
            await user.click(screen.getByRole('button', { name: 'Next' }));

            expect(await screen.findByText('Step 3 of 3')).toBeInTheDocument();
            await user.click(await screen.findByLabelText('Choir'));
            await user.click(screen.getByRole('button', { name: 'Add member' }));

            await waitFor(() => expect(membersApi.create).toHaveBeenCalledOnce());
            expect(membersApi.create).toHaveBeenCalledWith(
                expect.objectContaining({
                    first_name: 'Abena',
                    last_name: 'Asante',
                    gender: 'female',
                    phone: '0200987654',
                    status: 'active',
                    department_ids: ['dept-1'],
                    email: null,
                }),
            );
            await waitFor(() => expect(router.state.location.pathname).toBe('/members/new-member'));
        });

        it('will not leave a step until its boxes are right, and says what is wrong', async () => {
            const user = userEvent.setup();
            openNew();

            await user.click(await screen.findByRole('button', { name: 'Next' }));

            expect(await screen.findByText('Enter the first name')).toBeInTheDocument();
            expect(screen.getByText('Choose a gender')).toBeInTheDocument();
            expect(screen.getByText('Step 1 of 3')).toBeInTheDocument();
        });

        it('moves to the next step when Enter is pressed, and does not save early', async () => {
            const user = userEvent.setup();
            openNew();

            await user.type(await screen.findByLabelText(/First name/), 'Abena{Enter}');
            expect(await screen.findByText('Enter the last name')).toBeInTheDocument();
            expect(membersApi.create).not.toHaveBeenCalled();
        });

        it('opens on the step named in the address', async () => {
            openNew('/members/new?step=2');

            expect(await screen.findByLabelText(/^Phone number/)).toBeInTheDocument();
            expect(screen.getByText('Step 2 of 3')).toBeInTheDocument();
        });

        it('goes back a step without losing what was typed', async () => {
            const user = userEvent.setup();
            openNew();
            await completeStepOne(user);
            await screen.findByText('Step 2 of 3');

            await user.click(await screen.findByRole('button', { name: 'Back' }));

            expect(await screen.findByLabelText(/First name/)).toHaveValue('Abena');
        });

        it('asks for a role once a family is chosen', async () => {
            const user = userEvent.setup();
            openNew('/members/new?step=3');

            expect(screen.queryByLabelText(/Role in the family/)).not.toBeInTheDocument();
            await screen.findByRole('option', { name: 'Mensah Family' });
            await user.selectOptions(screen.getByLabelText('Family'), 'family-1');

            expect(await screen.findByLabelText(/Role in the family/)).toBeInTheDocument();
        });

        it('sends a national ID only when one was typed', async () => {
            const user = userEvent.setup();
            openNew();
            await completeStepOne(user);
            await user.type(await screen.findByLabelText(/^Phone number/), '0200987654');
            await user.click(screen.getByRole('button', { name: 'Next' }));
            await user.type(await screen.findByLabelText('ID type'), 'Ghana Card');
            await user.type(screen.getByLabelText('ID number'), 'GHA-123456789-0');
            await user.click(screen.getByRole('button', { name: 'Add member' }));

            await waitFor(() =>
                expect(membersApi.create).toHaveBeenCalledWith(
                    expect.objectContaining({ national_id_type: 'Ghana Card', national_id_number: 'GHA-123456789-0' }),
                ),
            );
        });

        it("shows the server's message beside the phone box and returns to the step that holds it", async () => {
            vi.mocked(membersApi.create).mockRejectedValue(
                new ApiError('Invalid', 422, { phone: ['A member with this phone number already exists.'] }),
            );
            const user = userEvent.setup();
            openNew();
            await completeStepOne(user);
            await user.type(await screen.findByLabelText(/^Phone number/), '0200987654');
            await user.click(screen.getByRole('button', { name: 'Next' }));
            await user.click(await screen.findByRole('button', { name: 'Add member' }));

            expect(await screen.findByText('A member with this phone number already exists.')).toBeInTheDocument();
            expect(screen.getByText('Step 2 of 3')).toBeInTheDocument();
        });

        it('cannot be saved twice while the first save is still running', async () => {
            vi.mocked(membersApi.create).mockReturnValue(new Promise(() => undefined));
            const user = userEvent.setup();
            openNew();
            await completeStepOne(user);
            await user.type(await screen.findByLabelText(/^Phone number/), '0200987654');
            await user.click(screen.getByRole('button', { name: 'Next' }));
            await user.click(await screen.findByRole('button', { name: 'Add member' }));

            expect(await screen.findByRole('button', { name: 'Saving…' })).toBeDisabled();
            expect(membersApi.create).toHaveBeenCalledOnce();
        });
    });

    describe('editing a member', () => {
        it('fills the form from the saved member', async () => {
            openEdit();

            expect(await screen.findByRole('heading', { level: 1, name: 'Edit Emmanuel Mensah-Boateng' })).toBeInTheDocument();
            expect(screen.getByLabelText(/First name/)).toHaveValue('Emmanuel');
            expect(screen.getByLabelText(/Last name/)).toHaveValue('Mensah-Boateng');
        });

        it('saves the changes to that member, without a starting status', async () => {
            const user = userEvent.setup();
            const { router } = openEdit();
            await user.click(await screen.findByRole('button', { name: 'Next' }));
            // Wait for the second step to show, or the second click lands on the first step's Next button.
            await screen.findByText('Step 2 of 3');
            await user.click(await screen.findByRole('button', { name: 'Next' }));
            await user.clear(await screen.findByLabelText('Occupation'));
            await user.type(screen.getByLabelText('Occupation'), 'Nurse');
            await user.click(screen.getByRole('button', { name: 'Save changes' }));

            await waitFor(() => expect(membersApi.update).toHaveBeenCalledOnce());
            const [id, input] = vi.mocked(membersApi.update).mock.calls[0] ?? [];
            expect(id).toBe('member-1');
            expect(input).toMatchObject({ occupation: 'Nurse', department_ids: ['dept-1'] });
            expect(input).not.toHaveProperty('status');
            await waitFor(() => expect(router.state.location.pathname).toBe('/members/member-1'));
        });

        it('does not show the starting status, which is changed from the profile', async () => {
            openEdit();
            await userEvent.click(await screen.findByRole('button', { name: 'Next' }));
            await screen.findByText('Step 2 of 3');
            await userEvent.click(await screen.findByRole('button', { name: 'Next' }));

            expect(await screen.findByLabelText('Occupation')).toBeInTheDocument();
            expect(screen.queryByLabelText('Starting status')).not.toBeInTheDocument();
        });

        it("refuses a deceased member's record", async () => {
            vi.mocked(membersApi.get).mockResolvedValue(member({ status: 'deceased', status_label: 'Deceased' }));
            openEdit();

            expect(await screen.findByRole('alert')).toHaveTextContent('cannot be changed');
        });

        it('says plainly when the member cannot be found', async () => {
            vi.mocked(membersApi.get).mockRejectedValue(new ApiError('Not found', 404));
            openEdit();

            expect(await screen.findByRole('alert')).toHaveTextContent('not found, or you do not have access');
        });
    });

    it('tells staff who may only view that they cannot add members', () => {
        openNew(ROUTES.MEMBER_NEW, [PERMISSIONS.MEMBERS_VIEW]);

        expect(screen.getByRole('alert')).toHaveTextContent('Your role cannot add or change members.');
    });
});
