import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { PledgeListItem } from '@/components/Giving/PledgeListItem';
import { pledge } from '@/test/fixtures';
import type { Pledge } from '@/types/giving';

function show(overrides: Partial<Pledge> = {}, onChange?: (changed: Pledge) => void) {
    render(
        <ul>
            <PledgeListItem pledge={pledge(overrides)} onChange={onChange} />
        </ul>,
    );
}

describe('PledgeListItem', () => {
    it('shows who, what for, which fund, when due, and the amounts as the API wrote them', () => {
        show();

        expect(screen.getByText('Building Fund 2026 pledge')).toBeInTheDocument();
        expect(screen.getByText(/Emmanuel Mensah · Building Fund · due 31 Dec 2026/)).toBeInTheDocument();
        // The amounts are in separate spans, so the sentence is read from the paragraph as a whole.
        const progress = screen.getByText(
            (_, element) => element?.tagName === 'P' && (element.textContent ?? '').startsWith('Paid GHS 300.00 of GHS 1,000.00'),
        );
        expect(progress).toHaveTextContent('GHS 700.00 outstanding · 30%');
    });

    it('shows the progress as a labelled progress bar', () => {
        show({ progress_percent: 45 });

        const bar = screen.getByRole('progressbar', { name: 'Building Fund 2026 pledge: paid so far' });
        expect(bar).toHaveAttribute('aria-valuenow', '45');
        expect(bar).toHaveAttribute('aria-valuetext', '45 percent paid');
    });

    it.each([
        ['active', 'Active'],
        ['fulfilled', 'Fulfilled'],
        ['partial', 'Partial'],
        ['lapsed', 'Lapsed'],
    ] as const)('names the %s status in words', (status, label) => {
        show({ status, status_label: label });

        expect(screen.getByText(label)).toBeInTheDocument();
    });

    it('has a Change button only when the person may change pledges, and hands the pledge back', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        show({}, onChange);

        await user.click(screen.getByRole('button', { name: 'Change' }));

        expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ id: 'pledge-1' }));
    });

    it('has no Change button without the permission', () => {
        show();

        expect(screen.queryByRole('button', { name: 'Change' })).not.toBeInTheDocument();
    });
});
