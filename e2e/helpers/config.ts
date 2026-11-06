import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

/** Where the app being tested lives. Override with environment variables to test another copy. */
export const PLATFORM_URL = process.env.E2E_PLATFORM_URL ?? 'http://church.test';
export const CHURCH_URL = process.env.E2E_CHURCH_URL ?? 'http://demo.church.test';

/** The seeded demo church's admin sign-in (config/demo.php), for the local copy only. */
export const DEMO_ADMIN_EMAIL = process.env.DEMO_ADMIN_EMAIL ?? 'admin@demo.church.test';
export const DEMO_ADMIN_PASSWORD = process.env.DEMO_ADMIN_PASSWORD ?? 'demo admin local passphrase';

/** Where Mailpit shows the emails the app sends in development (MAIL_MAILER=smtp, port 1025). Emailed sign-in codes are read here. */
export const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://127.0.0.1:8025';

/** The application log the texted sign-up codes are read from (the "log" SMS driver writes them there). */
export const LOG_FILE = process.env.E2E_LOG_FILE ?? path.resolve(here, '../../storage/logs/laravel.log');
