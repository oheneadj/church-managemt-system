import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MemberPicker } from '@/components/Members/MemberPicker';
import { membersApi } from '@/services/api/members';
import { memberSummary, pageOf } from '@/test/fixtures';
import type { MemberSummary } from '@/types/members';

vi.mock('@/services/api/members');

function setup(selected?: MemberSummary) {
    const onSelect = vi.fn();
    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <MemberPicker id="picker" label="Member" selected={selected} onSelect={onSelect} />
        </QueryClientProvider>,
    );

    return onSelect;
}

describe('MemberPicker', () => {
    beforeEach(() => {
        vi.mocked(membersApi.list).mockResolvedValue(
            pageOf([memberSummary({ id: 'm-1', full_name: 'Abena Asante', member_number: 'GRC-0042' })]),
        );
    });

    it('asks the server for nothing until something is searched for', () => {
        setup();

        expect(membersApi.list).not.toHaveBeenCalled();
    });

    it('searches, lists the matches and hands back the one that is chosen', async () => {
        const user = userEvent.setup();
        const onSelect = setup();

        await user.type(screen.getByLabelText('Member'), 'Abena');
        await user.click(screen.getByRole('button', { name: 'Search' }));
        await user.click(await screen.findByRole('button', { name: 'Choose' }));

        expect(membersApi.list).toHaveBeenCalledWith(expect.objectContaining({ search: 'Abena' }));
        expect(onSelect).toHaveBeenCalledWith(expect.objectContaining({ id: 'm-1' }));
    });

    it('says when nobody matches', async () => {
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([]));
        const user = userEvent.setup();
        setup();

        await user.type(screen.getByLabelText('Member'), 'Zed');
        await user.click(screen.getByRole('button', { name: 'Search' }));

        expect(await screen.findByText('No member found.')).toBeInTheDocument();
    });

    it('shows the chosen member with a Change button that clears the choice', async () => {
        const user = userEvent.setup();
        const onSelect = setup(memberSummary({ full_name: 'Abena Asante', member_number: 'GRC-0042' }));

        expect(screen.getByText('Abena Asante')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Change' }));

        await waitFor(() => expect(onSelect).toHaveBeenCalledWith(undefined));
    });
});
