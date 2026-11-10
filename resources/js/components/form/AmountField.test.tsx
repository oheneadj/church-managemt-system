import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm } from 'react-hook-form';
import { describe, expect, it, vi } from 'vitest';
import { AmountField } from '@/components/form/AmountField';

function Harness({ onValue, error }: { onValue?: (value: string) => void; error?: string }) {
    const form = useForm<{ amount: string }>({ defaultValues: { amount: '' } });
    const registered = form.register('amount');

    return (
        <form onSubmit={form.handleSubmit((values) => onValue?.(values.amount))}>
            <AmountField id="amount" label="Amount (GHS)" error={error} {...registered} />
            <button type="submit">Save</button>
        </form>
    );
}

describe('AmountField', () => {
    it('opens the numeric keypad on a phone and is not a number input', () => {
        render(<Harness />);

        const input = screen.getByLabelText('Amount (GHS)');
        expect(input).toHaveAttribute('inputmode', 'decimal');
        expect(input).toHaveAttribute('type', 'text');
        expect(input).toHaveAttribute('autocomplete', 'off');
    });

    it('does not let a letter be typed, so only the numbers stay', async () => {
        render(<Harness />);
        const input = screen.getByLabelText('Amount (GHS)');

        await userEvent.type(input, '2a0b.5x0');

        expect(input).toHaveValue('20.50');
    });

    it('cleans pasted text, a sign, a second point and a third decimal', async () => {
        render(<Harness />);
        const input = screen.getByLabelText('Amount (GHS)');

        await userEvent.click(input);
        await userEvent.paste('GHS -1,2.3.456');

        expect(input).toHaveValue('12.34');
    });

    it('gives the form the cleaned value, not what was typed', async () => {
        const onValue = vi.fn();
        render(<Harness onValue={onValue} />);

        await userEvent.type(screen.getByLabelText('Amount (GHS)'), 'abc75.5');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onValue).toHaveBeenCalledWith('75.5');
    });

    it('shows the error under the field and marks it invalid', () => {
        render(<Harness error="Enter the amount" />);

        expect(screen.getByLabelText('Amount (GHS)')).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByText('Enter the amount')).toBeInTheDocument();
    });
});
