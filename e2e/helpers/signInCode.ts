import fs from 'node:fs';
import { LOG_FILE, MAILPIT_URL } from './config';

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

/** The body of one email in Mailpit, as Mailpit's search lists it (newest first). */
interface MailpitSummary {
    ID: string;
    Created: string;
}

/** Asks Mailpit for something and returns its JSON, or null when Mailpit is not answering. */
async function mailpit<T>(path: string): Promise<T | null> {
    try {
        const response = await fetch(`${MAILPIT_URL}${path}`);

        return response.ok ? ((await response.json()) as T) : null;
    } catch {
        return null;
    }
}

/**
 * Waits for an email to `email`, sent after `after`, to arrive in Mailpit, and returns the six digits of the code in it.
 * The code sits alone on a line of the plain-text part (resources/views/mail/email-code.blade.php). It fails with a clear
 * message when none arrives, which usually means Mailpit is not running, MAIL_MAILER is not "smtp" on port 1025, or the
 * queue worker for the `critical` queue is not running.
 */
export async function emailedCodeFor(email: string, after: Date): Promise<string> {
    const deadline = Date.now() + WAIT_MS;

    while (Date.now() < deadline) {
        const found = await mailpit<{ messages: MailpitSummary[] }>(`/api/v1/search?query=${encodeURIComponent(`to:${email}`)}&limit=5`);
        const latest = found?.messages.find((message) => new Date(message.Created) > after);

        if (latest !== undefined) {
            const message = await mailpit<{ Text: string }>(`/api/v1/message/${latest.ID}`);
            const code = /^(\d{6})\s*$/m.exec(message?.Text ?? '')?.[1];

            if (code !== undefined) {
                return code;
            }
        }

        await new Promise((resolve) => setTimeout(resolve, POLL_MS));
    }

    throw new Error(
        `No emailed code reached Mailpit for ${email}. Is Mailpit running (${MAILPIT_URL}), MAIL_MAILER=smtp on port 1025, and the queue worker for the "critical" queue running?`,
    );
}
