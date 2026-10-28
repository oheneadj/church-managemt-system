import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { FormField } from '@/components/form/FormField';
import type { Option } from '@/constants/enums';

interface SelectFieldProps extends ComponentProps<'select'> {
    id: string;
    label: string;
    options: readonly Option<string>[];
    /** Text of the empty choice, for example "Any". Leave out when a value is required. */
    emptyLabel?: string;
    error?: string;
}

/**
 * A labelled drop-down for React Hook Form. It is the browser's own select, which is the easiest
 * control to use on a cheap phone and fully keyboard and screen-reader friendly.
 */
export function SelectField({ id, label, options, emptyLabel, error, required, ...selectProps }: SelectFieldProps) {
    return (
        <FormField id={id} label={label} error={error} required={required}>
            <div className="relative">
                <select
                    id={id}
                    required={required}
                    aria-required={required ? 'true' : undefined}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${id}-error` : undefined}
                    className="h-10 w-full appearance-none rounded-lg border border-input bg-transparent pr-9 pl-2.5 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                    {...selectProps}
                >
                    {emptyLabel !== undefined && <option value="">{emptyLabel}</option>}
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <ChevronDown
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>
        </FormField>
    );
}
