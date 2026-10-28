<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | One-time passcodes (docs/15-security.md)
    |--------------------------------------------------------------------------
    */
    'length' => 6,
    'ttl_seconds' => 600,
    'max_attempts' => 5,
    'lockout_seconds' => 900,

    // Staff sessions expire after 8 hours (docs/15-security.md).
    'staff_token_hours' => 8,

];
