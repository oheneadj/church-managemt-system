const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})/;

const DISPLAY = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' });

/**
 * A calendar date from the API ("2026-10-12") as "12 Oct", for a chart axis where the year would only crowd the labels.
 * It reads the day and month directly, as `formatDate` does, so the day never shifts with the viewer's timezone. Anything that
 * is not a date comes back unchanged.
 */
export function formatDayMonth(value: string): string {
    const match = DATE_ONLY.exec(value);

    if (match === null) {
        return value;
    }

    const [, year, month, day] = match;

    return DISPLAY.format(new Date(Number(year), Number(month) - 1, Number(day)));
}
