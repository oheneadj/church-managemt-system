import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DateFilter } from '@/components/form/DateFilter';

describe('DateFilter', () => {
    it("is the browser's own date input and shows the date it is given", () => {
        render(<DateFilter id="from" label="From" value="2026-10-01" onChange={vi.fn()} />);

        const box = screen.getByLabelText('From');
        expect(box).toHaveAttribute('type', 'date');
        expect(box).toHaveValue('2026-10-01');
    });

    it('applies a whole date as year, month, day', async () => {
        const onChange = vi.fn();
        render(<DateFilter id="from" label="From" value="" onChange={onChange} />);

        await userEvent.type(screen.getByLabelText('From'), '2026-10-05');

        expect(onChange).toHaveBeenLastCalledWith('2026-10-05');
    });

    it('removes the filter when the box is cleared', async () => {
        const onChange = vi.fn();
        render(<DateFilter id="from" label="From" value="2026-10-05" onChange={onChange} />);

        await userEvent.clear(screen.getByLabelText('From'));

        expect(onChange).toHaveBeenLastCalledWith('');
    });

    it('follows the address when it changes from outside, for example Clear filters', () => {
        const { rerender } = render(<DateFilter id="from" label="From" value="2026-10-05" onChange={vi.fn()} />);

        rerender(<DateFilter id="from" label="From" value="" onChange={vi.fn()} />);

        expect(screen.getByLabelText('From')).toHaveValue('');
    });
});
