/**
 * The time of day of a UTC timestamp, for the check-in list. Churches in Ghana are on UTC, and this is the one place
 * a timestamp becomes a time of day, so showing another timezone later means changing only this file (agreement section 30).
 */
export function formatClock(isoTimestamp: string): string {
    return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' }).format(new Date(isoTimestamp));
}
