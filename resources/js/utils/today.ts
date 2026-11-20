/**
 * Today's date as YYYY-MM-DD in the viewer's own calendar. It is the latest value allowed in date boxes
 * such as date of birth, and it matches how the API writes dates.
 */
export function today(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * The date a number of days from today (negative for the past) as YYYY-MM-DD, in the viewer's own calendar. It is used
 * for the limits on dates such as how far back a service may be dated.
 */
export function daysFromToday(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
