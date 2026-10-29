import { z } from 'zod';
import { DATE_BOX_HINT } from '@/constants/dates';
import { dateTextToIso } from '@/utils/dateText';

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

/** The limit as a date. */
function limitOf(limit: DateLimit): string {
    return typeof limit.date === 'function' ? limit.date() : limit.date;
}

/**
 * The check for a date box that holds day-first text ("25/12/2026"). An optional box may be empty; anything typed
 * must be a real date inside the limits. The value stays text; `dateTextToIso` turns it into the API's form on send.
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

            const iso = dateTextToIso(text);

            if (iso === null) {
                context.addIssue({ code: 'custom', message: `Enter a real date as ${DATE_BOX_HINT}` });
            } else if (notBefore !== undefined && iso < limitOf(notBefore)) {
                context.addIssue({ code: 'custom', message: notBefore.message });
            } else if (notAfter !== undefined && iso > limitOf(notAfter)) {
                context.addIssue({ code: 'custom', message: notAfter.message });
            }
        });
}
