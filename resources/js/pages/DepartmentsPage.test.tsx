import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { DepartmentsPage } from '@/pages/DepartmentsPage';
import { ApiError } from '@/services/ApiError';
import { departmentsApi } from '@/services/api/departments';
import { staffApi } from '@/services/api/staff';
import { department } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/departments');
vi.mock('@/services/api/staff');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const choir = department({ id: 'dept-1', name: 'Choir', description: 'Leads the singing.', members_count: 12 });
const ushers = department({ id: 'dept-2', name: 'Ushers', description: null, members_count: 1 });
const old = department({ id: 'dept-3', name: 'Old Group', description: null, is_active: false, members_count: 0 });

function open() {
    return renderPage(<DepartmentsPage />, { path: ROUTES.DEPARTMENTS, url: ROUTES.DEPARTMENTS, permissions: [PERMISSIONS.MEMBERS_EDIT] });
}

/** The list item for a department, found by its name. */
function row(name: string): HTMLElement {
    return screen.getByText(name).closest('li') as HTMLElement;
}

describe('DepartmentsPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(departmentsApi.all).mockResolvedValue([choir, ushers, old]);
        vi.mocked(staffApi.directory).mockResolvedValue([
            { id: 'user-1', name: 'Abena Pastor' },
            { id: 'user-2', name: 'Kofi Admin' },
        ]);
    });

    it('lists every department with what it does, how many members it has, and whether it is in use or retired', async () => {
        open();

        await screen.findByText('Choir');
        expect(within(row('Choir')).getByText('Leads the singing.')).toBeInTheDocument();
        expect(within(row('Choir')).getByText('12 members')).toBeInTheDocument();
        expect(within(row('Ushers')).getByText('1 member')).toBeInTheDocument();
        expect(within(row('Choir')).getByText('In use')).toBeInTheDocument();
        expect(within(row('Old Group')).getByText('Retired')).toBeInTheDocument();
        expect(within(row('Old Group')).getByRole('button', { name: 'Bring back' })).toBeInTheDocument();
    });

    it('adds a department, says so, and asks for the list again so it appears', async () => {
        vi.mocked(departmentsApi.create).mockResolvedValue(department({ id: 'dept-4', name: 'Youth' }));
        const user = userEvent.setup();
        open();
        await screen.findByText('Choir');

        await user.click(screen.getByRole('button', { name: 'Add a department' }));
        const dialog = await screen.findByRole('dialog');
        await user.type(within(dialog).getByLabelText(/^Name/), 'Youth');
        await user.click(within(dialog).getByRole('button', { name: 'Add department' }));

        await waitFor(() => expect(departmentsApi.create).toHaveBeenCalledWith({ name: 'Youth', description: null }));
        expect(toast.success).toHaveBeenCalledWith('Youth is added.');
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        await waitFor(() => expect(departmentsApi.all).toHaveBeenCalledTimes(2));
    });

    it('will not add a department without a name, and sends nothing', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Choir');

        await user.click(screen.getByRole('button', { name: 'Add a department' }));
        await user.click(await screen.findByRole('button', { name: 'Add department' }));

        expect(await screen.findByText('Enter the name of the department')).toBeInTheDocument();
        expect(departmentsApi.create).not.toHaveBeenCalled();
    });

    it('shows beside the box that a name is already taken, and stays open', async () => {
        vi.mocked(departmentsApi.create).mockRejectedValue(new ApiError('Invalid', 422, { name: ['The name has already been taken.'] }));
        const user = userEvent.setup();
        open();
        await screen.findByText('Choir');

        await user.click(screen.getByRole('button', { name: 'Add a department' }));
        await user.type(await screen.findByLabelText(/^Name/), 'Choir');
        await user.click(screen.getByRole('button', { name: 'Add department' }));

        expect(await screen.findByText('The name has already been taken.')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('changes a department from what it is now, sending only its name and description so the head is left alone', async () => {
        vi.mocked(departmentsApi.update).mockResolvedValue(choir);
        const user = userEvent.setup();
        open();
        await screen.findByText('Choir');

        await user.click(within(row('Choir')).getByRole('button', { name: 'Change' }));
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByLabelText(/^Name/)).toHaveValue('Choir');
        expect(within(dialog).getByLabelText('What it does')).toHaveValue('Leads the singing.');
        await user.clear(within(dialog).getByLabelText('What it does'));
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));

        await waitFor(() => expect(departmentsApi.update).toHaveBeenCalledWith('dept-1', { name: 'Choir', description: null }));
        expect(toast.success).toHaveBeenCalledWith('Changes saved.');
    });

    it('asks before retiring a department, says members stay in it, and only then retires it', async () => {
        vi.mocked(departmentsApi.setActive).mockResolvedValue({ ...choir, is_active: false });
        const user = userEvent.setup();
        open();
        await screen.findByText('Choir');

        await user.click(within(row('Choir')).getByRole('button', { name: 'Retire' }));
        const question = await screen.findByRole('alertdialog');
        expect(question).toHaveTextContent('Retire Choir?');
        expect(question).toHaveTextContent('Members already in it stay in it');
        expect(departmentsApi.setActive).not.toHaveBeenCalled();

        await user.click(within(question).getByRole('button', { name: 'Retire' }));

        await waitFor(() => expect(departmentsApi.setActive).toHaveBeenCalledWith('dept-1', false));
        expect(toast.success).toHaveBeenCalledWith('Choir is retired.');
    });

    it('retires nothing when the person cancels', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Choir');

        await user.click(within(row('Choir')).getByRole('button', { name: 'Retire' }));
        await user.click(await screen.findByRole('button', { name: 'Cancel' }));

        expect(departmentsApi.setActive).not.toHaveBeenCalled();
    });

    it('brings a retired department back without asking', async () => {
        vi.mocked(departmentsApi.setActive).mockResolvedValue({ ...old, is_active: true });
        const user = userEvent.setup();
        open();
        await screen.findByText('Old Group');

        await user.click(within(row('Old Group')).getByRole('button', { name: 'Bring back' }));

        await waitFor(() => expect(departmentsApi.setActive).toHaveBeenCalledWith('dept-3', true));
        expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
        expect(toast.success).toHaveBeenCalledWith('Old Group is back in use.');
    });

    it('says plainly when there are no departments', async () => {
        vi.mocked(departmentsApi.all).mockResolvedValue([]);
        open();

        expect(await screen.findByText('No departments yet')).toBeInTheDocument();
    });

    it('says what went wrong and tries again', async () => {
        vi.mocked(departmentsApi.all).mockRejectedValueOnce(new ApiError('The server is busy.', 500)).mockResolvedValue([choir]);
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByText('Choir')).toBeInTheDocument();
    });
});

describe('DepartmentsPage: the head of a department', () => {
    const headed = department({
        id: 'dept-5',
        name: 'Youth',
        head_user_id: 'user-1',
        head: { id: 'user-1', name: 'Abena Pastor' },
        members_count: 3,
    });

    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(departmentsApi.all).mockResolvedValue([headed, ushers]);
        vi.mocked(departmentsApi.create).mockResolvedValue(headed);
        vi.mocked(departmentsApi.update).mockResolvedValue(headed);
        vi.mocked(staffApi.directory).mockResolvedValue([
            { id: 'user-1', name: 'Abena Pastor' },
            { id: 'user-2', name: 'Kofi Admin' },
        ]);
    });

    it('names the head on the list, and says nothing for a department that has none', async () => {
        open();

        await screen.findByText('Youth');
        expect(within(row('Youth')).getByText('Head: Abena Pastor')).toBeInTheDocument();
        expect(within(row('Ushers')).queryByText(/^Head:/)).not.toBeInTheDocument();
    });

    it('offers the active staff as the head, and a department can start with nobody', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Youth');

        await user.click(screen.getByRole('button', { name: 'Add a department' }));
        const dialog = await screen.findByRole('dialog');
        const head = within(dialog).getByLabelText('Head of the department');
        await waitFor(() => expect(head).toBeEnabled());

        expect(
            within(head)
                .getAllByRole('option')
                .map((option) => option.textContent),
        ).toEqual(['Nobody yet', 'Abena Pastor', 'Kofi Admin']);
        expect(head).toHaveValue('');
    });

    it('sends the chosen head for a new department', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Youth');

        await user.click(screen.getByRole('button', { name: 'Add a department' }));
        const dialog = await screen.findByRole('dialog');
        await waitFor(() => expect(within(dialog).getByLabelText('Head of the department')).toBeEnabled());
        await user.type(within(dialog).getByLabelText(/^Name/), 'Media');
        await user.selectOptions(within(dialog).getByLabelText('Head of the department'), 'user-2');
        await user.click(within(dialog).getByRole('button', { name: 'Add department' }));

        await waitFor(() =>
            expect(departmentsApi.create).toHaveBeenCalledWith({ name: 'Media', description: null, head_user_id: 'user-2' }),
        );
    });

    it('starts a change from the current head, and saving the name alone does not send the head', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Youth');

        await user.click(within(row('Youth')).getByRole('button', { name: 'Change' }));
        const dialog = await screen.findByRole('dialog');
        await waitFor(() => expect(within(dialog).getByLabelText('Head of the department')).toHaveValue('user-1'));
        await user.type(within(dialog).getByLabelText(/^Name/), ' Team');
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));

        await waitFor(() => expect(departmentsApi.update).toHaveBeenCalledWith('dept-5', { name: 'Youth Team', description: null }));
    });

    it('sends the new head when it is changed, and null when the head is taken away', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Youth');

        await user.click(within(row('Youth')).getByRole('button', { name: 'Change' }));
        let dialog = await screen.findByRole('dialog');
        await waitFor(() => expect(within(dialog).getByLabelText('Head of the department')).toHaveValue('user-1'));
        await user.selectOptions(within(dialog).getByLabelText('Head of the department'), 'user-2');
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));
        await waitFor(() =>
            expect(departmentsApi.update).toHaveBeenLastCalledWith('dept-5', { name: 'Youth', description: null, head_user_id: 'user-2' }),
        );
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

        await user.click(within(row('Youth')).getByRole('button', { name: 'Change' }));
        dialog = await screen.findByRole('dialog');
        await waitFor(() => expect(within(dialog).getByLabelText('Head of the department')).toHaveValue('user-1'));
        await user.selectOptions(within(dialog).getByLabelText('Head of the department'), '');
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));
        await waitFor(() =>
            expect(departmentsApi.update).toHaveBeenLastCalledWith('dept-5', { name: 'Youth', description: null, head_user_id: null }),
        );
    });

    it('keeps a head who is no longer active as a choice, marked, and does not send them back when they are unchanged', async () => {
        vi.mocked(staffApi.directory).mockResolvedValue([{ id: 'user-2', name: 'Kofi Admin' }]);
        const user = userEvent.setup();
        open();
        await screen.findByText('Youth');

        await user.click(within(row('Youth')).getByRole('button', { name: 'Change' }));
        const dialog = await screen.findByRole('dialog');
        const head = within(dialog).getByLabelText('Head of the department');
        await waitFor(() => expect(head).toBeEnabled());
        expect(within(head).getByRole('option', { name: 'Abena Pastor (not active)' })).toBeInTheDocument();
        expect(head).toHaveValue('user-1');

        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));

        await waitFor(() => expect(departmentsApi.update).toHaveBeenCalledWith('dept-5', { name: 'Youth', description: null }));
    });

    it('cannot change the head, and never clears it, when the list of staff cannot be loaded', async () => {
        vi.mocked(staffApi.directory).mockRejectedValue(new ApiError('The server is busy.', 500));
        const user = userEvent.setup();
        open();
        await screen.findByText('Youth');

        await user.click(within(row('Youth')).getByRole('button', { name: 'Change' }));
        const dialog = await screen.findByRole('dialog');

        expect(await within(dialog).findByText(/The list of staff could not be loaded/)).toBeInTheDocument();
        expect(within(dialog).getByLabelText('Head of the department')).toBeDisabled();

        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));

        await waitFor(() => expect(departmentsApi.update).toHaveBeenCalledWith('dept-5', { name: 'Youth', description: null }));
    });
});
