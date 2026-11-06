<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    | How many calls are allowed before a caller gets a 429 (docs/08, Rate Limits).
    | Sending a code costs money, so each kind of code has its own tight limits below.
    */
    'api_per_minute' => 300,
    'uploads_per_minute' => 20,

    // Staff sign-in by email (D-054). Emailing a code costs a little and can be abused, so it is limited apart from the
    // password check: one code a minute and five an hour for one email, 20 an hour from one address. Guessing passwords
    // is limited per address, and checking an emailed code is limited like the text codes.
    'login_code_per_minute' => 1,
    'login_code_per_hour' => 5,
    'login_code_per_hour_per_ip' => 20,
    'login_attempts_per_minute_per_ip' => 20,
    'login_verify_per_minute_per_ip' => 10,

    // A member checking in by QR code is texted a code, which costs money, so it is limited per phone and service, and per IP address.
    'checkin_otp_per_minute' => 1,
    'checkin_otp_per_hour' => 5,
    'checkin_otp_per_hour_per_ip' => 20,
    'checkin_verify_per_minute_per_ip' => 10,

    // Inviting staff emails a code to a stranger's address, so it is limited per church. The church admin does it rarely.
    'staff_invites_per_hour' => 20,

    // Exports read a lot of personal data and use a worker, so each staff member may start a few an hour (QA-11 TC-11-12).
    'exports_per_hour' => 5,

    // A write that carries an Idempotency-Key is remembered for this long, so a retry gets the first answer back. Two requests with one key wait for each other for up to the lock time.
    'idempotency_ttl_minutes' => 1440,
    'idempotency_lock_seconds' => 10,

    // Meta delivers every church's WhatsApp messages to one address, from many of its own servers, so this is per address and generous.
    'webhooks_per_minute' => 600,

    // Starting a church sends a code to a stranger's phone, so it is limited like sign-in: tightly per number and per IP address.
    'onboarding_otp_per_minute' => 1,
    'onboarding_otp_per_hour' => 3,
    'onboarding_otp_per_hour_per_ip' => 10,
    'onboarding_register_per_hour_per_ip' => 10,

    /*
    |--------------------------------------------------------------------------
    | Page size
    |--------------------------------------------------------------------------
    | Every list is paginated (docs/08, Pagination). A caller may ask for a page size up to the maximum.
    */
    'per_page_default' => 25,
    'per_page_max' => 100,

];
