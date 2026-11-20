import { z } from 'zod';

interface DateLimit {
    /** The limit as YYYY-MM-DD, or a function that gives it, so "today" is worked out when the form is checked and not when the page loaded. */
    date: string | (() => string);
    /** What to tell the person when the date is beyond it. */
    message: string;
}

interface DateBoxOptions {
    /** When set the box may not be empty, and this is what to say if it is. */
    required?: string;
    /** The earliest date allowed. */
    notBefore?: DateLimit;
    /** The latest date allowed. */
    notAfter?: DateLimit;
}

/** Whether a text is a real day written as `YYYY-MM-DD` (31 February is not). */
function isRealDate(text: string): boolean {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);

    if (match === null) {
        return false;
    }

    const [year, month, day] = [Number(match[1]), Number(match[2]), Number(match[3])];
    const date = new Date(year, month - 1, day);

    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
}

/** The limit as a date. */
function limitOf(limit: DateLimit): string {
    return typeof limit.date === 'function' ? limit.date() : limit.date;
}

/**
 * The check for a date box (the browser's date input, whose value is `YYYY-MM-DD`). An optional box may be empty;
 * anything in it must be a real date inside the limits. The value is already in the API's form, so it is sent as it is.
 */
export function dateBox({ required, notBefore, notAfter }: DateBoxOptions = {}) {
    return z
        .string()
        .trim()
        .superRefine((text, context) => {
            if (text === '') {
                if (required !== undefined) {
                    context.addIssue({ code: 'custom', message: required });
                }

                return;
            }

            if (!isRealDate(text)) {
                context.addIssue({ code: 'custom', message: 'Enter a real date' });
            } else if (notBefore !== undefined && text < limitOf(notBefore)) {
                context.addIssue({ code: 'custom', message: notBefore.message });
            } else if (notAfter !== undefined && text > limitOf(notAfter)) {
                context.addIssue({ code: 'custom', message: notAfter.message });
            }
        });
}
