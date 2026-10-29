import type { ChangeEvent, ComponentProps } from 'react';
import { TextField } from '@/components/form/TextField';
import { DATE_BOX_HINT } from '@/constants/dates';
import { maskDateText } from '@/utils/dateText';

interface DateFieldProps extends Omit<ComponentProps<'input'>, 'type'> {
    id: string;
    label: string;
    error?: string;
}

/**
 * A labelled date box that always reads day first (dd/mm/yyyy), whatever language the browser or phone uses. The
 * browser's own date picker is not used because it shows the month first on many devices. The slashes are put in
 * as the person types, and the value stays text: validate it with `dateBox` and turn it into the API's form with
 * `dateTextToIso`. Spread the result of `register('name')` onto it, as on any other field.
 */
export function DateField({ onChange, ...props }: DateFieldProps) {
    function mask(event: ChangeEvent<HTMLInputElement>) {
        event.target.value = maskDateText(event.target.value);
        onChange?.(event);
    }

    return <TextField inputMode="numeric" placeholder={DATE_BOX_HINT} maxLength={10} onChange={mask} {...props} />;
}
