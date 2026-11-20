import type { ChangeEvent, ComponentProps } from 'react';
import { TextField } from '@/components/form/TextField';
import { sanitizeAmountInput } from '@/utils/money';

type AmountFieldProps = Omit<ComponentProps<typeof TextField>, 'type' | 'inputMode'>;

/**
 * A labelled input for an amount of cedis, for React Hook Form (spread `register('name')` onto it). Only digits and one decimal
 * point can be typed or pasted, with at most two decimals, so a letter never appears. It is a text input, not a number input, because
 * a number input still lets letters such as "e" through on a computer. On a phone it opens the numeric keypad.
 */
export function AmountField({ onChange, ...props }: AmountFieldProps) {
    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const cleaned = sanitizeAmountInput(event.target.value);

        if (cleaned !== event.target.value) {
            event.target.value = cleaned;
        }

        onChange?.(event);
    };

    return <TextField type="text" inputMode="decimal" autoComplete="off" placeholder="0.00" {...props} onChange={handleChange} />;
}
