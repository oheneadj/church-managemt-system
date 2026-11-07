import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { STAFF_PERMISSIONS } from '@/constants/enums';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { StaffPage } from '@/pages/StaffPage';
import { staffApi } from '@/services/api/staff';
import { ApiError } from '@/services/ApiError';
import { pageOf, staffMember } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/staff');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const abena = staffMember({ id: 'staff-1', name: 'Abena Pastor', email: 'abena@example.com', roles: ['pastor'] });
const kofi = staffMember({
    id: 'staff-2',
    name: 'Kofi Admin',
    email: 'kofi@example.com',
    phone: '+233200000001',
    roles: ['church-admin'],
    last_login_at: null,
});
const esi = staffMember({ id: 'staff-3', name: 'Esi Finance', email: 'esi@example.com', roles: ['finance-secretary'], is_active: false });

function show(rows = [abena, kofi, esi]) {
    vi.mocked(staffApi.list).mockResolvedValue(pageOf(rows));

    return renderPage(<StaffPage />, { path: ROUTES.STAFF, url: ROUTES.STAFF, permissions: [PERMISSIONS.STAFF_MANAGE] });
}

describe('StaffPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    it('lists each person with their role, whether they are active, and when they last signed in', async () => {
        show();

        const row = (await screen.findByText('Abena Pastor')).closest('li') as HTMLElement;
        expect(within(row).getByText('abena@example.com')).toBeInTheDocument();
        expect(within(row).getByText('Pastor')).toBeInTheDocument();
        expect(within(row).getByText('Active')).toBeInTheDocument();
        expect(within(row).getByText(/Last signed in 5 Nov 2026/)).toBeInTheDocument();

        expect(within(screen.getByText('Kofi Admin').closest('li') as HTMLElement).getByText('Never signed in')).toBeInTheDocument();
        expect(within(screen.getByText('Esi Finance').closest('li') as HTMLElement).getByText('Deactivated')).toBeInTheDocument();
    });

    it('shows the extra permissions someone was given, in words', async () => {
        show([staffMember({ extra_permissions: ['members.export'] })]);

        expect(await screen.findByText(/Also allowed: Export the member list/)).toBeInTheDocument();
    });

    it('says when the list fails, and tries again', async () => {
        vi.mocked(staffApi.list)
            .mockRejectedValueOnce(new ApiError('Something broke.', 500))
            .mockResolvedValue(pageOf([abena]));
        renderPage(<StaffPage />, { path: ROUTES.STAFF, url: ROUTES.STAFF, permissions: [PERMISSIONS.STAFF_MANAGE] });

        expect(await screen.findByText('Something broke.')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByText('Abena Pastor')).toBeInTheDocument();
    });

    describe('inviting', () => {
        async function fillInvite(overrides: { email?: string; phone?: string } = {}) {
            await userEvent.click(await screen.findByRole('button', { name: 'Invite staff' }));
            const dialog = await screen.findByRole('dialog');
            await userEvent.type(within(dialog).getByLabelText(/^Name/), 'Yaw Boateng');
            await userEvent.type(within(dialog).getByLabelText(/^Email/), overrides.email ?? 'Yaw@Example.com');
            await userEvent.type(within(dialog).getByLabelText(/^Phone/), overrides.phone ?? '0244123456');
            await userEvent.selectOptions(within(dialog).getByLabelText(/^Role/), 'finance-secretary');

            return dialog;
        }

        it('sends the invitation once, with a key, and refreshes the list', async () => {
            vi.mocked(staffApi.invite).mockResolvedValue(staffMember({ id: 'staff-9', name: 'Yaw Boateng' }));
            show();
            const dialog = await fillInvite();

            await userEvent.click(within(dialog).getByRole('button', { name: 'Send invitation' }));

            await waitFor(() => expect(staffApi.invite).toHaveBeenCalledTimes(1));
            const [input, key] = vi.mocked(staffApi.invite).mock.lastCall ?? [];
            expect(input).toEqual({
                name: 'Yaw Boateng',
                email: 'yaw@example.com',
                phone: '0244123456',
                role: 'finance-secretary',
            });
            expect(key).toMatch(/^[0-9a-f]{32}$/);
            await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
            expect(staffApi.list).toHaveBeenCalledTimes(2);
        });

        it('checks the fields before calling the API', async () => {
            show();
            await userEvent.click(await screen.findByRole('button', { name: 'Invite staff' }));
            const dialog = await screen.findByRole('dialog');

            await userEvent.type(within(dialog).getByLabelText(/^Email/), 'not-an-email');
            await userEvent.click(within(dialog).getByRole('button', { name: 'Send invitation' }));

            expect(await within(dialog).findByText('Enter their name')).toBeInTheDocument();
            expect(within(dialog).getByText('Enter a valid email address')).toBeInTheDocument();
            expect(within(dialog).getByText('Enter a valid phone number')).toBeInTheDocument();
            expect(within(dialog).getByLabelText(/^Role/)).toHaveAccessibleDescription('Choose a role');
            expect(staffApi.invite).not.toHaveBeenCalled();
        });

        it("puts the server's message next to the box it belongs to", async () => {
            vi.mocked(staffApi.invite).mockRejectedValue(
                new ApiError('invalid', 422, { email: ['A staff member with this email already exists.'] }),
            );
            show();
            const dialog = await fillInvite();

            await userEvent.click(within(dialog).getByRole('button', { name: 'Send invitation' }));

            expect(await within(dialog).findByLabelText(/^Email/)).toHaveAccessibleDescription(
                'A staff member with this email already exists.',
            );
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('offers every role', async () => {
            show();
            await userEvent.click(await screen.findByRole('button', { name: 'Invite staff' }));

            const options = within(await screen.findByLabelText(/^Role/))
                .getAllByRole('option')
                .map((option) => option.textContent);

            expect(options).toEqual(['Choose a role', 'Church admin', 'Pastor', 'Finance secretary', 'Department head', 'Youth pastor']);
        });
    });

    describe('changing', () => {
        async function openChange(name: string) {
            const row = (await screen.findByText(name)).closest('li') as HTMLElement;
            await userEvent.click(within(row).getByRole('button', { name: 'Change' }));

            return screen.findByRole('dialog');
        }

        it('starts from what they have and sends the new name, phone and role', async () => {
            vi.mocked(staffApi.update).mockResolvedValue(abena);
            show();
            const dialog = await openChange('Abena Pastor');
            expect(within(dialog).getByLabelText(/^Name/)).toHaveValue('Abena Pastor');
            expect(within(dialog).getByLabelText(/^Role/)).toHaveValue('pastor');

            await userEvent.clear(within(dialog).getByLabelText(/^Name/));
            await userEvent.type(within(dialog).getByLabelText(/^Name/), 'Abena Owusu');
            await userEvent.selectOptions(within(dialog).getByLabelText(/^Role/), 'youth-pastor');
            await userEvent.click(within(dialog).getByRole('button', { name: 'Save changes' }));

            await waitFor(() => expect(staffApi.update).toHaveBeenCalledTimes(1));
            expect(staffApi.update).toHaveBeenCalledWith('staff-1', { name: 'Abena Owusu', phone: '+233244123456', role: 'youth-pastor' });
            await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        });

        it('does not let the email be changed', async () => {
            show();
            const dialog = await openChange('Abena Pastor');

            expect(within(dialog).queryByLabelText(/^Email/)).not.toBeInTheDocument();
            expect(within(dialog).getByText('abena@example.com')).toBeInTheDocument();
        });

        it('explains why the last church admin cannot lose the role', async () => {
            const { toast } = await import('sonner');
            vi.mocked(staffApi.update).mockRejectedValue(new ApiError('A church needs at least one active church admin.', 409));
            show();
            const dialog = await openChange('Kofi Admin');

            await userEvent.selectOptions(within(dialog).getByLabelText(/^Role/), 'pastor');
            await userEvent.click(within(dialog).getByRole('button', { name: 'Save changes' }));

            await waitFor(() => expect(toast.error).toHaveBeenCalledWith('A church needs at least one active church admin.'));
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });
    });

    describe('switching someone off and on', () => {
        it('asks before deactivating, then does it', async () => {
            vi.mocked(staffApi.setActive).mockResolvedValue({ ...abena, is_active: false });
            show();
            const row = (await screen.findByText('Abena Pastor')).closest('li') as HTMLElement;

            await userEvent.click(within(row).getByRole('button', { name: 'Deactivate' }));
            expect(staffApi.setActive).not.toHaveBeenCalled();
            const confirm = await screen.findByRole('alertdialog');
            expect(within(confirm).getByText(/signed out at once/)).toBeInTheDocument();
            await userEvent.click(within(confirm).getByRole('button', { name: 'Deactivate' }));

            await waitFor(() => expect(staffApi.setActive).toHaveBeenCalledWith('staff-1', false));
        });

        it('does nothing when the question is cancelled', async () => {
            show();
            const row = (await screen.findByText('Abena Pastor')).closest('li') as HTMLElement;

            await userEvent.click(within(row).getByRole('button', { name: 'Deactivate' }));
            await userEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Cancel' }));

            expect(staffApi.setActive).not.toHaveBeenCalled();
        });

        it('reactivates without asking', async () => {
            vi.mocked(staffApi.setActive).mockResolvedValue({ ...esi, is_active: true });
            show();
            const row = (await screen.findByText('Esi Finance')).closest('li') as HTMLElement;

            await userEvent.click(within(row).getByRole('button', { name: 'Reactivate' }));

            await waitFor(() => expect(staffApi.setActive).toHaveBeenCalledWith('staff-3', true));
        });

        it('says why the last church admin cannot be deactivated', async () => {
            const { toast } = await import('sonner');
            vi.mocked(staffApi.setActive).mockRejectedValue(new ApiError('A church needs at least one active church admin.', 409));
            show();
            const row = (await screen.findByText('Kofi Admin')).closest('li') as HTMLElement;

            await userEvent.click(within(row).getByRole('button', { name: 'Deactivate' }));
            await userEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Deactivate' }));

            await waitFor(() => expect(toast.error).toHaveBeenCalledWith('A church needs at least one active church admin.'));
        });
    });
});

describe('StaffPage: permissions on top of the role', () => {
    const pastor = staffMember({ id: 'staff-1', name: 'Abena Pastor', roles: ['pastor'], extra_permissions: ['giving.export'] });
    const admin = staffMember({
        id: 'staff-2',
        name: 'Kofi Admin',
        roles: ['church-admin'],
        role_permissions: STAFF_PERMISSIONS.map((permission) => permission.value),
        extra_permissions: [],
    });

    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(staffApi.list).mockResolvedValue(pageOf([pastor, admin]));
        vi.mocked(staffApi.setPermissions).mockResolvedValue(pastor);
    });

    async function openFor(name: string) {
        const user = userEvent.setup();
        show([pastor, admin]);
        const row = (await screen.findByText(name)).closest('li') as HTMLElement;
        await user.click(within(row).getByRole('button', { name: 'Permissions' }));

        return { user, dialog: await screen.findByRole('dialog') };
    }

    it('ticks what the role gives (and cannot be changed), what was granted on top, and leaves the rest unticked', async () => {
        const { dialog } = await openFor('Abena Pastor');

        const fromRole = within(dialog).getByLabelText(/View members/);
        expect(fromRole).toBeChecked();
        expect(fromRole).toBeDisabled();
        expect(fromRole.closest('div')).toHaveTextContent('View members (from their role)');
        expect(within(dialog).getByLabelText('Export giving')).toBeChecked();
        expect(within(dialog).getByLabelText('Export giving')).toBeEnabled();
        expect(within(dialog).getByLabelText('Export the member list')).not.toBeChecked();
    });

    it('will not save until something is changed', async () => {
        const { user, dialog } = await openFor('Abena Pastor');

        expect(within(dialog).getByRole('button', { name: 'Save permissions' })).toBeDisabled();

        await user.click(within(dialog).getByLabelText('Export the member list'));
        expect(within(dialog).getByRole('button', { name: 'Save permissions' })).toBeEnabled();

        await user.click(within(dialog).getByLabelText('Export the member list'));
        expect(within(dialog).getByRole('button', { name: 'Save permissions' })).toBeDisabled();
    });

    it('grants a newly ticked permission and says it applies the next time they use the app', async () => {
        const { user, dialog } = await openFor('Abena Pastor');

        await user.click(within(dialog).getByLabelText('Export the member list'));
        await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }));

        await waitFor(() => expect(staffApi.setPermissions).toHaveBeenCalledWith('staff-1', { grant: ['members.export'] }));
        expect(toast.success).toHaveBeenCalledWith('Permissions saved. They apply the next time they use the app.');
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });

    it('takes away an extra that is unticked', async () => {
        const { user, dialog } = await openFor('Abena Pastor');

        await user.click(within(dialog).getByLabelText('Export giving'));
        await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }));

        await waitFor(() => expect(staffApi.setPermissions).toHaveBeenCalledWith('staff-1', { revoke: ['giving.export'] }));
    });

    it('shows every permission as given by the role for a church admin, with nothing to change', async () => {
        const { dialog } = await openFor('Kofi Admin');

        for (const checkbox of within(dialog).getAllByRole('checkbox')) {
            expect(checkbox).toBeChecked();
            expect(checkbox).toBeDisabled();
        }
        expect(within(dialog).getByRole('button', { name: 'Save permissions' })).toBeDisabled();
    });

    it("shows the server's refusal and stays open", async () => {
        vi.mocked(staffApi.setPermissions).mockRejectedValue(new ApiError('Something broke.', 500));
        const { user, dialog } = await openFor('Abena Pastor');

        await user.click(within(dialog).getByLabelText('Export the member list'));
        await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }));

        await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Something broke.'));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('sends nothing when the person cancels', async () => {
        const { user, dialog } = await openFor('Abena Pastor');

        await user.click(within(dialog).getByLabelText('Export the member list'));
        await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));

        expect(staffApi.setPermissions).not.toHaveBeenCalled();
    });
});
