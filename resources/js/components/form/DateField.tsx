import type { ComponentProps } from 'react';
import { TextField } from '@/components/form/TextField';

interface DateFieldProps extends Omit<ComponentProps<'input'>, 'type'> {
    id: string;
    label: string;
    error?: string;
}

/**
 * A labelled date box: the browser's own date input (`type="date"`), so the person picks from a calendar or types in the
 * order their phone or computer uses. Its value is always `YYYY-MM-DD`, which is what the API takes, so nothing needs
 * converting. Pass `min` and `max` (as `YYYY-MM-DD`) to stop the calendar offering a date the server would refuse; validate
 * the value with `dateBox`. Spread the result of `register('name')` onto it, as on any other field.
 */
export function DateField(props: DateFieldProps) {
    return <TextField type="date" {...props} />;
}
