import fs from 'node:fs';
import { LOG_FILE } from './config';

const WAIT_MS = 20_000;
const POLL_MS = 500;

/** How big the log is now. Take this before asking for a code, so an older code is never picked up by mistake. */
export function logPosition(): number {
    return fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;
}

/** Everything written to the log after `position`, or an empty string when nothing was. */
function logAfter(position: number): string {
    const size = fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;

    if (size <= position) {
        return '';
    }

    const fd = fs.openSync(LOG_FILE, 'r');
    const buffer = Buffer.alloc(size - position);
    fs.readSync(fd, buffer, 0, buffer.length, position);
    fs.closeSync(fd);

    return buffer.toString('utf8');
}

/** Polls the log after `position` until `pattern` matches, and returns its first group (the six digits). */
async function waitForCode(position: number, pattern: RegExp, failure: string): Promise<string> {
    const deadline = Date.now() + WAIT_MS;

    while (Date.now() < deadline) {
        const match = [...logAfter(position).matchAll(pattern)].at(-1);

        if (match?.[1]) {
            return match[1];
        }

        await new Promise((resolve) => setTimeout(resolve, POLL_MS));
    }

    throw new Error(failure);
}

/**
 * Waits for the "log" SMS driver to write a code after `position`, and returns the six digits (the sign-up phone code).
 * It fails with a clear message when none arrives, which usually means the queue worker is not running.
 */
export function codeWrittenAfter(position: number): Promise<string> {
    return waitForCode(
        position,
        /code is: (\d{6})/g,
        'No sign-in code appeared in the log. Is the queue worker for the "critical" queue running (php artisan queue:work --queue=critical)?',
    );
}

/**
 * Waits for the "log" mail driver to write an emailed code after `position`, and returns the six digits. The code sits
 * alone on a line of the plain-text part of the email (resources/views/mail/email-code.blade.php).
 */
export function emailedCodeAfter(position: number): Promise<string> {
    return waitForCode(
        position,
        /^(\d{6})\s*$/gm,
        'No emailed code appeared in the log. Is the queue worker for the "critical" queue running and MAIL_MAILER set to "log"?',
    );
}
