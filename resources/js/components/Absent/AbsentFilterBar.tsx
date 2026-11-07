import { SelectField } from '@/components/form/SelectField';
import { ABSENCE_THRESHOLD_MAX, ABSENCE_THRESHOLD_MIN } from '@/constants/settings';

/** The choices 2 to 12, the range the server accepts. */
const CHOICES = Array.from({ length: ABSENCE_THRESHOLD_MAX - ABSENCE_THRESHOLD_MIN + 1 }, (_, index) => {
    const number = ABSENCE_THRESHOLD_MIN + index;

    return { value: String(number), label: `${number} services in a row` };
});

interface AbsentFilterBarProps {
    /** The number chosen, or null for the church's own setting. */
    consecutive: number | null;
    /** The church's own setting, for the first choice's words, once it is known. */
    churchSetting?: number;
    onChange: (value: string) => void;
}

/** The one choice on the page: how many Sunday services in a row a member must have missed. */
export function AbsentFilterBar({ consecutive, churchSetting, onChange }: AbsentFilterBarProps) {
    return (
        <section aria-label="Choices">
            <SelectField
                id="absent-consecutive"
                label="Missed"
                options={CHOICES}
                emptyLabel={churchSetting === undefined ? 'As set in Settings' : `As set in Settings (${churchSetting} services in a row)`}
                value={consecutive === null ? '' : String(consecutive)}
                onChange={(event) => onChange(event.target.value)}
            />
        </section>
    );
}
