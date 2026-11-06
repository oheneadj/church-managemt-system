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

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy (D-056)
    |--------------------------------------------------------------------------
    | `page` is for the React page: scripts only from us and carrying the request's nonce, no inline script, no eval,
    | nothing framed. Styles may be inline because the toast library adds a <style> element at run time and a nonce
    | on style-src would make browsers ignore 'unsafe-inline'; scripts are the part that matters. `api` is for every
    | response that is not a page. Photo links point at the storage host, added from the R2 endpoint.
    | `exempt_paths` get no policy: the API docs page loads a third-party viewer and is for staff only (D-035).
    */
    'csp' => [
        'exempt_paths' => ['docs/*'],

        'api' => [
            'default-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
        ],

        'page' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ],
    ],

];
