<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Demo church sign-in (local development only)
|--------------------------------------------------------------------------
|
| The sign-in of the demo church's admin that `DatabaseSeeder` creates. The seeder runs this only in the `local`
| environment, so these values never reach staging or production. The end-to-end tests (e2e/helpers/config.ts) read
| the same two variables, so change them in both places by setting the environment variables, not by editing code.
*/

return [

    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@demo.church.test'),

    'admin_password' => env('DEMO_ADMIN_PASSWORD', 'demo admin local passphrase'),

    // The demo platform admin (D-069), for the same local-only purpose: the end-to-end tests sign in to the platform side with it.
    'platform_admin_email' => env('DEMO_PLATFORM_ADMIN_EMAIL', 'owner@platform.church.test'),
    'platform_admin_password' => env('DEMO_PLATFORM_ADMIN_PASSWORD', 'demo platform local passphrase'),

];
