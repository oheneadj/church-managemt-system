import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';
import { MemberTable } from '@/components/Members/MemberTable';
import { memberSummary } from '@/test/fixtures';

// The member directory as a table, for a wide screen (docs/design-system/interactions.md, section 14): real headers, the member's
// name as the link, a decorative photo, status as words, and View and Edit buttons named for the member, Edit only for those who may.

const members = [
    memberSummary(),
    memberSummary({
        id: 'member-2',
        full_name: 'Abena Asante',
        member_number: 'GRC-0002',
        phone: '+233201234567',
        departments: [],
        status: 'prospect',
        status_label: 'Prospect',
    }),
];

function table(props: { canEdit?: boolean; refreshing?: boolean } = {}) {
    return render(
        <MemoryRouter>
            <MemberTable members={members} canEdit={props.canEdit ?? false} refreshing={props.refreshing} />
        </MemoryRouter>,
    );
}

describe('MemberTable', () => {
    it('is a table named Members with a header for each column', () => {
        table();

        expect(screen.getByRole('table', { name: 'Members' })).toBeInTheDocument();
        expect(screen.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
            'Member',
            'Phone',
            'Departments',
            'Status',
            'Actions',
        ]);
    });

    it('shows each member on a row: the name as a link to the profile, the number, phone, departments and status', () => {
        table();
        const row = screen.getByRole('row', { name: /Emmanuel Mensah-Boateng/ });

        expect(within(row).getByRole('link', { name: 'Emmanuel Mensah-Boateng' })).toHaveAttribute('href', '/members/member-1');
        expect(within(row).getByText(members[0]!.member_number)).toBeInTheDocument();
        expect(within(row).getByText(members[0]!.phone)).toHaveClass('tabular-nums');
        expect(within(row).getByText(members[0]!.status_label)).toBeInTheDocument();
        for (const department of members[0]!.departments) {
            expect(row).toHaveTextContent(department);
        }
    });

    it('says "None" for a member with no department, and shows a prospect as words', () => {
        table();
        const row = screen.getByRole('row', { name: /Abena Asante/ });

        expect(within(row).getByText('None')).toBeInTheDocument();
        expect(within(row).getByText('Prospect')).toBeInTheDocument();
    });

    it('draws the photo as decoration, because the name is written beside it', () => {
        table();

        expect(screen.queryAllByRole('img')).toHaveLength(0);
        expect(document.querySelector('[aria-hidden="true"] [data-slot="profile-image"]')).not.toBeNull();
    });

    it('gives every member a View button named for them, leading to the profile', () => {
        table();

        expect(screen.getByRole('link', { name: 'View Abena Asante' })).toHaveAttribute('href', '/members/member-2');
        expect(screen.getAllByRole('link', { name: /^View / })).toHaveLength(2);
    });

    it('gives an Edit button only to someone who may edit, leading to the edit page', () => {
        const { unmount } = table();
        expect(screen.queryByRole('link', { name: /^Edit / })).toBeNull();
        unmount();

        table({ canEdit: true });
        expect(screen.getByRole('link', { name: 'Edit Abena Asante' })).toHaveAttribute('href', '/members/member-2/edit');
        expect(screen.getAllByRole('link', { name: /^Edit / })).toHaveLength(2);
    });

    it('keeps its rows, dimmed and marked busy, while newer figures load', () => {
        table({ refreshing: true });

        expect(screen.getByRole('table')).toHaveAttribute('aria-busy', 'true');
        expect(screen.getByRole('table')).toHaveClass('opacity-60');
        expect(screen.getByRole('link', { name: 'Emmanuel Mensah-Boateng' })).toBeInTheDocument();
    });
});
