/**
 * Today's date as YYYY-MM-DD in the viewer's own calendar. It is the latest value allowed in date boxes
 * such as date of birth, and it matches how the API writes dates.
 */
export function today(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}
