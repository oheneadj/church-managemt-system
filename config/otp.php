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

    // Staff passwords (D-054): five wrong passwords for one email lock it for 15 minutes, like wrong codes.
    'password_max_attempts' => 5,
    'password_lockout_seconds' => 900,

    // Staff sessions expire after 8 hours (docs/15-security.md).
    'staff_token_hours' => 8,

];
