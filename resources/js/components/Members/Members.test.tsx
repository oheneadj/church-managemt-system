import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';
import { MemberAvatar } from '@/components/Members/MemberAvatar';
import { MemberListItem } from '@/components/Members/MemberListItem';
import { MemberStatusBadge } from '@/components/Members/MemberStatusBadge';
import { memberSummary } from '@/test/fixtures';

describe('MemberStatusBadge', () => {
    it('shows the status label for every status', () => {
        render(<MemberStatusBadge status="transferred" label="Transferred" />);

        expect(screen.getByText('Transferred')).toBeInTheDocument();
    });
});

describe('MemberAvatar', () => {
    it('shows initials when the member has no photo', () => {
        render(<MemberAvatar fullName="Abena Asante" photoUrl={null} />);

        expect(screen.getByText('AA')).toBeInTheDocument();
    });
});

describe('MemberListItem', () => {
    it('shows who the member is and links to their profile', () => {
        render(
            <MemoryRouter>
                <ul>
                    <MemberListItem member={memberSummary({ id: 'm-9', full_name: 'Abena Asante', departments: ['Choir', 'Ushers'] })} />
                </ul>
            </MemoryRouter>,
        );

        const link = screen.getByRole('link', { name: /Abena Asante/ });
        expect(link).toHaveAttribute('href', '/members/m-9');
        expect(link).toHaveTextContent('GRC-0001');
        expect(link).toHaveTextContent('+233244123456');
        expect(link).toHaveTextContent('Choir, Ushers');
        expect(link).toHaveTextContent('Active');
    });
});
