import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';

interface FormFieldProps {
    /** Ties the label, the control and the error message together. */
    id: string;
    label: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
}

/**
 * A label above a control with its error message below. The error appears only when there is one and is
 * announced to screen readers; the control points to it with aria-describedby={`${id}-error`} while it shows.
 */
export function FormField({ id, label, error, required = false, children }: FormFieldProps) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id}>
                {label}
                {required && <span aria-hidden="true"> *</span>}
            </Label>
            {children}
            {error && (
                <p id={`${id}-error`} role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
