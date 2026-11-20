const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})/;

const DISPLAY = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

/**
 * Shows a calendar date from the API ("2023-04-15") as "15 Apr 2023". It reads the year, month and day
 * directly instead of building a UTC date, so the day never shifts with the viewer's timezone.
 * Anything that is not a date comes back unchanged.
 */
export function formatDate(value: string): string {
    const match = DATE_ONLY.exec(value);

    if (match === null) {
        return value;
    }

    const [, year, month, day] = match;

    return DISPLAY.format(new Date(Number(year), Number(month) - 1, Number(day)));
}
