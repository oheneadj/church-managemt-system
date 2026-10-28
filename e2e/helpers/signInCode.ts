import fs from 'node:fs';
import { LOG_FILE } from './config';

const WAIT_MS = 20_000;
const POLL_MS = 500;

/** How big the log is now. Take this before asking for a code, so an older code is never picked up by mistake. */
export function logPosition(): number {
    return fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;
}

/**
 * Waits for the "log" SMS driver to write a sign-in code after `position`, and returns the six digits.
 * It fails with a clear message when none arrives, which usually means the queue worker is not running.
 */
export async function codeWrittenAfter(position: number): Promise<string> {
    const deadline = Date.now() + WAIT_MS;

    while (Date.now() < deadline) {
        const size = fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;

        if (size > position) {
            const fd = fs.openSync(LOG_FILE, 'r');
            const buffer = Buffer.alloc(size - position);
            fs.readSync(fd, buffer, 0, buffer.length, position);
            fs.closeSync(fd);

            const match = [...buffer.toString('utf8').matchAll(/code is: (\d{6})/g)].at(-1);
            if (match?.[1]) return match[1];
        }

        await new Promise((resolve) => setTimeout(resolve, POLL_MS));
    }

    throw new Error(
        'No sign-in code appeared in the log. Is the queue worker for the "critical" queue running (php artisan queue:work --queue=critical)?',
    );
}
