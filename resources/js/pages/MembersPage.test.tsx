import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { MembersPage } from '@/pages/MembersPage';
import { ApiError } from '@/services/ApiError';
import { departmentsApi } from '@/services/api/departments';
import { membersApi } from '@/services/api/members';
import { memberSummary, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/members');
vi.mock('@/services/api/departments');

function open(url: string = ROUTES.MEMBERS, permissions: Permission[] = []) {
    return renderPage(<MembersPage />, { path: ROUTES.MEMBERS, url, permissions });
}

describe('MembersPage', () => {
    beforeEach(() => {
        vi.mocked(departmentsApi.list).mockResolvedValue(
            pageOf([{ id: 'dept-1', name: 'Choir', description: null, head_user_id: null, is_active: true }]),
        );
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
