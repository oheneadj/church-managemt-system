<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Demo church and platform sign-in (local development and staging only)
|--------------------------------------------------------------------------
|
| The sign-ins that `DatabaseSeeder` creates for the demo church's admin and for a platform admin. The seeder never runs this
| in production. In local development the defaults below are used. On a staging (test) site there are NO defaults: a guessable
| password on a public site would be a hole, and the sign-in code is emailed, so the email must be a real inbox. Set all of
| them in the environment, or no demo data is made. The end-to-end tests (e2e/helpers/config.ts) read the same variables.
*/

// A default only when running locally; null elsewhere, so a deployed site never has a password nobody chose.
$local = env('APP_ENV') === 'local';

return [

    'admin_email' => env('DEMO_ADMIN_EMAIL', $local ? 'admin@demo.church.test' : null),

    'admin_password' => env('DEMO_ADMIN_PASSWORD', $local ? 'demo admin local passphrase' : null),

    // The demo platform admin (D-069), for the same local-only purpose: the end-to-end tests sign in to the platform side with it.
    'platform_admin_email' => env('DEMO_PLATFORM_ADMIN_EMAIL', $local ? 'owner@platform.church.test' : null),
    'platform_admin_password' => env('DEMO_PLATFORM_ADMIN_PASSWORD', $local ? 'demo platform local passphrase' : null),

];
