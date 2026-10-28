<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    | In staging and production the app sits behind Coolify's reverse proxy, which ends TLS and talks to the
    | container over a private Docker network. Laravel only believes X-Forwarded-Proto / -For / -Host from
    | addresses listed here (read by Illuminate\Http\Middleware\TrustProxies). Without this the app thinks every
    | request is plain HTTP: links come out as http://, and secure cookies and client IPs are wrong.
    |
    | The default is the private address ranges Docker networks use, so only the proxy can be believed.
    | Do not use "*" unless the container is unreachable except through the proxy.
    */
    'proxies' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
    ))),

];
