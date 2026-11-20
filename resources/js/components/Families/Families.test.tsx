import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';
import { FamilyListItem } from '@/components/Families/FamilyListItem';
import { FamilyMemberList } from '@/components/Families/FamilyMemberList';
import { family } from '@/test/fixtures';
import type { FamilyMember } from '@/types/members';

function familyMember(overrides: Partial<FamilyMember>): FamilyMember {
    return {
        id: 'm1',
        member_number: 'GRC-0001',
        full_name: 'Abena Mensah',
        gender: 'female',
        gender_label: 'Female',
        status: 'active',
        status_label: 'Active',
        family_role: 'spouse',
        family_role_label: 'Spouse',
        ...overrides,
    };
}

describe('FamilyListItem', () => {
    it('shows the name and how many members, linking to the family', () => {
        render(
            <MemoryRouter>
                <ul>
                    <FamilyListItem family={family({ id: 'f-3', name: 'Asante Family', members_count: 4 })} />
                </ul>
            </MemoryRouter>,
        );

        const link = screen.getByRole('link', { name: /Asante Family/ });
        expect(link).toHaveAttribute('href', '/families/f-3');
        expect(link).toHaveTextContent('4 members');
    });

    it('says "1 member" in the singular', () => {
        render(
            <MemoryRouter>
                <ul>
                    <FamilyListItem family={family({ members_count: 1 })} />
                </ul>
            </MemoryRouter>,
        );

        expect(screen.getByText('1 member')).toBeInTheDocument();
    });
});

describe('FamilyMemberList', () => {
    it('lists the head of the household first, then the rest by name', () => {
        render(
            <MemoryRouter>
                <FamilyMemberList
                    members={[
                        familyMember({ id: 'a', full_name: 'Zainab Mensah', family_role: 'child', family_role_label: 'Child' }),
                        familyMember({ id: 'b', full_name: 'Kofi Mensah', family_role: 'head', family_role_label: 'Head' }),
                        familyMember({ id: 'c', full_name: 'Abena Mensah' }),
                    ]}
                />
            </MemoryRouter>,
        );

        const names = screen.getAllByRole('link').map((link) => link.querySelector('p')?.textContent);
        expect(names).toEqual(['Kofi Mensah', 'Abena Mensah', 'Zainab Mensah']);
    });

    it("shows each member's role and status and links to their profile", () => {
        render(
            <MemoryRouter>
                <FamilyMemberList members={[familyMember({ id: 'm-7', status: 'inactive', status_label: 'Inactive' })]} />
            </MemoryRouter>,
        );

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('href', '/members/m-7');
        expect(link).toHaveTextContent('GRC-0001 · Spouse');
        expect(link).toHaveTextContent('Inactive');
    });
});
