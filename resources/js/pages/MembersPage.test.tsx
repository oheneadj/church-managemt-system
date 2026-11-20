import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { MembersPage } from '@/pages/MembersPage';
import { ApiError } from '@/services/ApiError';
import { departmentsApi } from '@/services/api/departments';
import { exportsApi } from '@/services/api/exports';
import { membersApi } from '@/services/api/members';
import { department, memberSummary, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/members');
vi.mock('@/services/api/departments');
vi.mock('@/services/api/exports');

function open(url: string = ROUTES.MEMBERS, permissions: Permission[] = []) {
    return renderPage(<MembersPage />, { path: ROUTES.MEMBERS, url, permissions });
}

describe('MembersPage', () => {
    beforeEach(() => {
        vi.mocked(departmentsApi.list).mockResolvedValue(pageOf([department({ name: 'Choir' })]));
        vi.mocked(membersApi.list).mockResolvedValue(
            pageOf([
                memberSummary(),
                memberSummary({
                    id: 'member-2',
                    full_name: 'Abena Asante',
                    member_number: 'GRC-0002',
                    status: 'prospect',
                    status_label: 'Prospect',
                }),
            ]),
        );
    });

    it('lists the members with how many were found', async () => {
        open();

        expect(await screen.findByRole('link', { name: /Emmanuel Mensah-Boateng/ })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Abena Asante/ })).toBeInTheDocument();
        expect(screen.getByText('2 found')).toBeInTheDocument();
        expect(membersApi.list).toHaveBeenCalledWith({ search: '', status: '', gender: '', department_id: '', page: 1 });
    });

    it('shows loading placeholders while the first page loads', () => {
        vi.mocked(membersApi.list).mockReturnValue(new Promise(() => undefined));
        open();

        expect(document.querySelector('[aria-busy="true"]')).toBeInTheDocument();
    });

    it('searches when the user presses Search, and keeps the search in the address', async () => {
        const user = userEvent.setup();
        const { router } = open();
        await screen.findByRole('link', { name: /Emmanuel/ });

        await user.type(screen.getByLabelText('Search members'), 'abena');
        await user.click(screen.getByRole('button', { name: 'Search' }));

        await waitFor(() => expect(membersApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ search: 'abena', page: 1 })));
        expect(router.state.location.search).toBe('?search=abena');
    });

    it('starts from the filters in the address, so a shared link shows the same view', async () => {
        open('/members?status=inactive&gender=female&department_id=dept-1&page=2');

        await screen.findByRole('link', { name: /Emmanuel/ });
        expect(membersApi.list).toHaveBeenCalledWith({
            search: '',
            status: 'inactive',
            gender: 'female',
            department_id: 'dept-1',
            page: 2,
        });
        expect(screen.getByLabelText('Status')).toHaveValue('inactive');
        expect(screen.getByLabelText('Gender')).toHaveValue('female');
    });

    it('filters by status and offers the departments of the church', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByRole('link', { name: /Emmanuel/ });

        expect(await within(screen.getByLabelText('Department')).findByRole('option', { name: 'Choir' })).toBeInTheDocument();
        await user.selectOptions(screen.getByLabelText('Status'), 'inactive');

        await waitFor(() => expect(membersApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ status: 'inactive' })));
    });

    it('clears every filter', async () => {
        const user = userEvent.setup();
        const { router } = open('/members?status=inactive&search=abena');
        await screen.findByRole('link', { name: /Emmanuel/ });

        await user.click(screen.getByRole('button', { name: 'Clear filters' }));

        await waitFor(() => expect(router.state.location.search).toBe(''));
    });

    it('moves to the next page', async () => {
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()], { last_page: 3, total: 60 }));
        const user = userEvent.setup();
        const { router } = open();
        await screen.findByText('Page 1 of 3');

        await user.click(screen.getByRole('button', { name: 'Next' }));

        await waitFor(() => expect(router.state.location.search).toBe('?page=2'));
    });

    it('says plainly when nothing matches', async () => {
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([]));
        open('/members?search=zzz');

        expect(await screen.findByRole('heading', { name: 'No members found' })).toBeInTheDocument();
    });

    it('shows the error and lets the user try again', async () => {
        vi.mocked(membersApi.list).mockRejectedValueOnce(new ApiError('Cannot reach the server. Check your connection.', 0));
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('Cannot reach the server');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByRole('link', { name: /Emmanuel/ })).toBeInTheDocument();
    });

    it('offers Add member only to staff who may edit members', async () => {
        open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EDIT]);

        expect(await screen.findByRole('link', { name: 'Add member' })).toHaveAttribute('href', ROUTES.MEMBER_NEW);
    });

    it('hides Add member from staff who may only view', async () => {
        open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_VIEW]);
        await screen.findByRole('link', { name: /Emmanuel/ });

        expect(screen.queryByRole('link', { name: 'Add member' })).not.toBeInTheDocument();
    });
});

describe('MembersPage: export', () => {
    beforeEach(() => {
        vi.mocked(departmentsApi.list).mockResolvedValue(pageOf([]));
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()]));
        vi.mocked(exportsApi.members).mockResolvedValue({ export_id: 'e1', message: 'Export queued.' });
    });

    it('offers the departments page to staff who may edit members, and to nobody else', async () => {
        const first = open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByText('Emmanuel Mensah-Boateng');
        expect(screen.getByRole('link', { name: 'Departments' })).toHaveAttribute('href', '/departments');
        first.unmount();

        open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EXPORT]);
        await screen.findByText('Emmanuel Mensah-Boateng');
        expect(screen.queryByRole('link', { name: 'Departments' })).not.toBeInTheDocument();
    });

    it('shows the Export list button only to staff who may export', async () => {
        const first = open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EXPORT]);
        await screen.findByText('Emmanuel Mensah-Boateng');
        expect(screen.getByRole('button', { name: 'Export list' })).toBeInTheDocument();
        first.unmount();

        open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByText('Emmanuel Mensah-Boateng');
        expect(screen.queryByRole('button', { name: 'Export list' })).not.toBeInTheDocument();
    });

    it('exports with the filters now on screen, so the file holds what the list shows', async () => {
        const user = userEvent.setup();
        open('/members?status=active&search=Abena&page=2', [PERMISSIONS.MEMBERS_EXPORT]);
        await screen.findByText('Emmanuel Mensah-Boateng');

        await user.click(screen.getByRole('button', { name: 'Export list' }));
        await user.click(await screen.findByRole('button', { name: 'Email me the link' }));

        await waitFor(() => expect(exportsApi.members).toHaveBeenCalledTimes(1));
        expect(vi.mocked(exportsApi.members).mock.calls[0]?.[0]).toMatchObject({
            search: 'Abena',
            status: 'active',
            gender: '',
            department_id: '',
        });
    });
});

describe('MembersPage on a wide screen', () => {
    const original = window.matchMedia;

    beforeEach(() => {
        window.matchMedia = ((query: string) =>
            ({
                matches: true,
                media: query,
                addEventListener: () => undefined,
                removeEventListener: () => undefined,
            }) as unknown as MediaQueryList) as typeof window.matchMedia;
        vi.mocked(departmentsApi.list).mockResolvedValue(pageOf([department({ name: 'Choir' })]));
        vi.mocked(membersApi.list).mockResolvedValue(
            pageOf([memberSummary(), memberSummary({ id: 'member-2', full_name: 'Abena Asante', member_number: 'GRC-0002' })], {
                total: 2,
            }),
        );
    });

    afterEach(() => {
        window.matchMedia = original;
    });

    it('draws the members as a table, not as a list of cards', async () => {
        open();

        const table = await screen.findByRole('table', { name: 'Members' });
        expect(within(table).getByRole('link', { name: 'Abena Asante' })).toHaveAttribute('href', '/members/member-2');
        expect(screen.queryByRole('list')).toBeNull();
        expect(screen.getByText('2 found')).toBeInTheDocument();
    });

    it('shows a table of placeholder rows while the first page loads, and says it is loading once', () => {
        vi.mocked(membersApi.list).mockReturnValue(new Promise(() => undefined));
        open();

        const table = screen.getByRole('table');
        expect(table).toHaveAttribute('aria-busy', 'true');
        expect(within(table).getByRole('status')).toHaveTextContent('Loading');
        expect(within(table).queryAllByRole('link')).toHaveLength(0);
    });

    it('keeps the filters in the address and asks for the same page', async () => {
        open('/members?status=inactive&page=2');

        await screen.findByRole('table', { name: 'Members' });
        expect(membersApi.list).toHaveBeenCalledWith({ search: '', status: 'inactive', gender: '', department_id: '', page: 2 });
        expect(screen.getByLabelText('Status')).toHaveValue('inactive');
    });

    it('offers View to everyone and Edit only to those who may edit members', async () => {
        const first = open(ROUTES.MEMBERS, []);
        await screen.findByRole('table', { name: 'Members' });
        expect(screen.getAllByRole('link', { name: /^View / })).toHaveLength(2);
        expect(screen.queryByRole('link', { name: /^Edit / })).toBeNull();
        first.unmount();

        open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EDIT]);
        expect(await screen.findAllByRole('link', { name: /^Edit / })).toHaveLength(2);
    });

    it('still says plainly when nobody is found, with the way to add a member for those who may', async () => {
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([], { total: 0 }));
        open(ROUTES.MEMBERS, [PERMISSIONS.MEMBERS_EDIT]);

        expect(await screen.findByText('No members found')).toBeInTheDocument();
        expect(screen.queryByRole('table')).toBeNull();
        expect(screen.getAllByRole('link', { name: 'Add member' }).length).toBeGreaterThan(0);
    });

    it('still pages through the members', async () => {
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()], { total: 60, current_page: 1, last_page: 3 }));
        const user = userEvent.setup();
        open();

        await screen.findByRole('table', { name: 'Members' });
        await user.click(screen.getByRole('button', { name: 'Next' }));

        await waitFor(() => expect(membersApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 })));
    });
});
