/** "12 Nov 2026, 14:30" in the viewer's own time zone, always with the day first. */
const DISPLAY = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

/**
 * A date and time from the API (an ISO timestamp) as text for a screen. Anything that is not a date comes back as it was.
 */
export function formatDateTime(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : DISPLAY.format(date);
}
