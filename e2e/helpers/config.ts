import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

/** Where the app being tested lives. Override with environment variables to test another copy. */
export const PLATFORM_URL = process.env.E2E_PLATFORM_URL ?? 'http://church.test';
export const CHURCH_URL = process.env.E2E_CHURCH_URL ?? 'http://demo.church.test';

/** The seeded demo church's admin (database/seeders/DatabaseSeeder.php), for the local copy only. */
export const DEMO_ADMIN_PHONE = process.env.E2E_ADMIN_PHONE ?? '0200000000';

/** The application log the sign-in codes are read from (the "log" SMS driver writes them there). */
export const LOG_FILE = process.env.E2E_LOG_FILE ?? path.resolve(here, '../../storage/logs/laravel.log');
