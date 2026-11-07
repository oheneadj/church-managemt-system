import { TextField } from '@/components/form/TextField';

interface DateFilterProps {
    id: string;
    label: string;
    /** The date now in effect as YYYY-MM-DD, or empty for none. */
    value: string;
    /** Called with a date as YYYY-MM-DD, or with empty when the box is cleared. */
    onChange: (iso: string) => void;
}

/**
 * A date box for a filter (not part of a form), the browser's own date input. A date is applied once the browser reports a
 * whole real date, and clearing the box removes the filter; a half-picked date does nothing. When the address changes
 * from outside (Clear filters, the back button) the box follows it, because it shows the value it is given.
 */
export function DateFilter({ id, label, value, onChange }: DateFilterProps) {
    return <TextField id={id} label={label} type="date" value={value} onChange={(event) => onChange(event.target.value)} />;
}
