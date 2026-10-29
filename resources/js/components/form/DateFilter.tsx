import { useState } from 'react';
import { TextField } from '@/components/form/TextField';
import { DATE_BOX_HINT } from '@/constants/dates';
import { dateTextToIso, isoToDateText, maskDateText } from '@/utils/dateText';

interface DateFilterProps {
    id: string;
    label: string;
    /** The date now in effect as YYYY-MM-DD, or empty for none. */
    value: string;
    /** Called with a real date as YYYY-MM-DD, or with empty when the box is cleared. */
    onChange: (iso: string) => void;
}

/**
 * A day-first date box for a filter (not part of a form): typing a whole real date applies it, and clearing the box
 * removes the filter. A half-typed date does nothing until it is complete, and one that is not a real day says so.
 */
export function DateFilter({ id, label, value, onChange }: DateFilterProps) {
    const [text, setText] = useState(isoToDateText(value));
    const [seen, setSeen] = useState(value);

    // When the address changes from outside (Clear filters, back button), the box follows it.
    if (value !== seen) {
        setSeen(value);
        setText(isoToDateText(value));
    }

    const complete = text.length === DATE_BOX_HINT.length;
    const iso = dateTextToIso(text);

    function change(next: string) {
        const masked = maskDateText(next);
        setText(masked);

        if (masked === '') {
            setSeen('');
            onChange('');
        } else if (masked.length === DATE_BOX_HINT.length) {
            const real = dateTextToIso(masked);

            if (real !== null) {
                setSeen(real);
                onChange(real);
            }
        }
    }

    return (
        <TextField
            id={id}
            label={label}
            inputMode="numeric"
            placeholder={DATE_BOX_HINT}
            maxLength={DATE_BOX_HINT.length}
            value={text}
            onChange={(event) => change(event.target.value)}
            error={complete && iso === null ? `Enter a real date as ${DATE_BOX_HINT}` : undefined}
        />
    );
}
