/**
 * Dates as people type them, day first: "25/12/2026". The app keeps dates as "2026-12-25" (what the API uses), so these
 * turn one into the other. Nothing here depends on the browser's language, which is why the date boxes use them
 * instead of the browser's own date picker (that one shows month first on many phones and computers).
 */

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const DATE_TEXT = /^(\d{2})\/(\d{2})\/(\d{4})$/;

/** Whether this year, month and day is a real day (31 February is not). */
function isRealDay(year: number, month: number, day: number): boolean {
    const date = new Date(year, month - 1, day);

    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
}

/**
 * Keeps only the digits, at most eight, and puts the slashes in as the person types: "2512" shows "25/12" and
 * "25122026" shows "25/12/2026". A slash only appears once a digit follows it, so backspace never gets stuck.
 */
export function maskDateText(raw: string): string {
    const digits = raw.replace(/\D/g, '').slice(0, 8);

    return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter((part) => part !== '').join('/');
}

/** "2026-12-25" shown as "25/12/2026". Anything that is not a date comes back empty. */
export function isoToDateText(iso: string): string {
    const match = ISO_DATE.exec(iso);

    return match === null ? '' : `${match[3]}/${match[2]}/${match[1]}`;
}

/** "25/12/2026" as "2026-12-25", or null when it is not complete or not a real day. */
export function dateTextToIso(text: string): string | null {
    const match = DATE_TEXT.exec(text.trim());

    if (match === null || !isRealDay(Number(match[3]), Number(match[2]), Number(match[1]))) {
        return null;
    }

    return `${match[3]}-${match[2]}-${match[1]}`;
}
