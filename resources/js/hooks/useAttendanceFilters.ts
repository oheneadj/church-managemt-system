import { useSearchParams } from 'react-router';
import { REPORT_GROUPINGS } from '@/constants/enums';
import { ATTENDANCE_WEEKS_DEFAULT, ATTENDANCE_WEEK_CHOICES } from '@/constants/reports';
import type { AttendanceFilters } from '@/types/reports';

/**
 * The attendance report's choices, kept in the address bar so a refresh and a shared link show the same chart. A value that
 * is not one of the choices is ignored.
 */
export function useAttendanceFilters() {
    const [params, setParams] = useSearchParams();
    const weeks = ATTENDANCE_WEEK_CHOICES.find((choice) => choice.value === params.get('weeks'));

    const filters: AttendanceFilters = {
        weeks: weeks === undefined ? ATTENDANCE_WEEKS_DEFAULT : Number(weeks.value),
        groupBy: REPORT_GROUPINGS.find((option) => option.value === params.get('group'))?.value ?? 'week',
    };

    function setFilter(name: 'weeks' | 'group', value: string) {
        setParams(
            (current) => {
                current.set(name, value);

                return current;
            },
            { replace: true },
        );
    }

    return { filters, setFilter };
}
