<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant base domain
    |--------------------------------------------------------------------------
    | Churches live on {slug}.{base_domain}. Locally this is "localhost", so
    | demo.localhost resolves to the church with slug "demo".
    */
    'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),

];
