import type { ComponentProps } from 'react';
import { FormField } from '@/components/form/FormField';
import { Textarea } from '@/components/ui/textarea';

interface TextAreaFieldProps extends ComponentProps<'textarea'> {
    id: string;
    label: string;
    error?: string;
}

/**
 * A labelled multi-line input for React Hook Form: spread the result of `register('name')` onto it.
 */
export function TextAreaField({ id, label, error, ...textareaProps }: TextAreaFieldProps) {
    return (
        <FormField id={id} label={label} error={error}>
            <Textarea
                id={id}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                {...textareaProps}
            />
        </FormField>
    );
}
