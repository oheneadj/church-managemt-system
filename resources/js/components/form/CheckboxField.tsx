import type { ComponentProps } from 'react';

interface CheckboxFieldProps extends ComponentProps<'input'> {
    id: string;
    label: string;
}

/**
 * A labelled tick box for React Hook Form: spread the result of `register('name')` onto it. Several boxes with the
 * same name and different values give an array of the ticked ones. It is the browser's own checkbox, large enough to tap.
 */
export function CheckboxField({ id, label, ...inputProps }: CheckboxFieldProps) {
    return (
        <div className="flex items-center gap-2">
            <input id={id} type="checkbox" className="size-5 accent-primary" {...inputProps} />
            <label htmlFor={id} className="text-sm">
                {label}
            </label>
        </div>
    );
}
