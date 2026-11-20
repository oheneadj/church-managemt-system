import { REPORT_MONTHS_DEFAULT, REPORT_MONTH_CHOICES } from '@/constants/reports';

/** How many months a monthly report looks back, from the address bar. A value that is not one of the choices is ignored. */
export function monthsFrom(value: string | null): number {
    const choice = REPORT_MONTH_CHOICES.find((candidate) => candidate.value === value);

    return choice === undefined ? REPORT_MONTHS_DEFAULT : Number(choice.value);
}
