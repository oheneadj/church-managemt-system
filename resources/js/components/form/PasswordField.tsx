import type { ComponentProps } from 'react';
import { FormField } from '@/components/form/FormField';
import { PasswordInput } from '@/components/ui/PasswordInput';

interface PasswordFieldProps extends Omit<ComponentProps<'input'>, 'type'> {
    id: string;
    label: string;
    error?: string;
}

/**
 * A labelled password input for React Hook Form: spread the result of `register('name')` onto it. Set `autoComplete` to
 * `current-password` when signing in and `new-password` when choosing one, so password managers do the right thing.
 */
export function PasswordField({ id, label, error, required, ...inputProps }: PasswordFieldProps) {
    return (
        <FormField id={id} label={label} error={error} required={required}>
            <PasswordInput
                id={id}
                className="h-10"
                required={required}
                aria-required={required ? 'true' : undefined}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                {...inputProps}
            />
        </FormField>
    );
}
