import type { ComponentProps } from 'react';
import { FormField } from '@/components/form/FormField';
import { Input } from '@/components/ui/input';

interface TextFieldProps extends ComponentProps<'input'> {
    id: string;
    label: string;
    error?: string;
}

/**
 * A labelled text input for React Hook Form: spread the result of `register('name')` onto it.
 */
export function TextField({ id, label, error, required, ...inputProps }: TextFieldProps) {
    return (
        <FormField id={id} label={label} error={error} required={required}>
            <Input
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
