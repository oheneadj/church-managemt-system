const MONTH_FORMAT = /^(\d{4})-(\d{2})/;

const STYLES = {
    short: new Intl.DateTimeFormat('en-GB', { month: 'short', year: '2-digit' }),
    long: new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }),
};

/**
 * A month from the API ("2026-10" or "2026-10-01") as "Oct 26" for a chart axis or "October 2026" for a title. It reads the
 * year and month directly, so the month never shifts with the viewer's timezone. Anything else comes back unchanged.
 */
export function formatMonth(value: string, style: keyof typeof STYLES): string {
    const match = MONTH_FORMAT.exec(value);

    if (match === null) {
        return value;
    }

    return STYLES[style].format(new Date(Number(match[1]), Number(match[2]) - 1, 1));
}
