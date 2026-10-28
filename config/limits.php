<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    | How many calls are allowed before a caller gets a 429 (docs/08, Rate Limits).
    | Sending a sign-in code costs money, so it has its own tight limits.
    */
    'api_per_minute' => 300,
    'uploads_per_minute' => 20,
    'otp_request_per_minute' => 1,
    'otp_request_per_hour' => 5,
    'otp_request_per_hour_per_ip' => 20,
    'otp_verify_per_minute_per_ip' => 10,

    /*
    |--------------------------------------------------------------------------
    | Page size
    |--------------------------------------------------------------------------
    | Every list is paginated (docs/08, Pagination). A caller may ask for a page size up to the maximum.
    */
    'per_page_default' => 25,
    'per_page_max' => 100,

];
