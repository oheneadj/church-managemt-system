<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Response security headers (docs/15, Transport Security)
    |--------------------------------------------------------------------------
    | Sent on every response by the SecurityHeaders middleware.
    */
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        // Modern browsers ignore this header; "0" switches off the old, buggy filter.
        'X-XSS-Protection' => '0',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ],

    /*
    | HSTS tells browsers to use HTTPS only. It is sent only on HTTPS requests, because browsers ignore it
    | over plain HTTP. One year, including every church subdomain.
    */
    'hsts' => 'max-age=31536000; includeSubDomains',

];
