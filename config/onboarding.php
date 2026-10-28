<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Church web address (slug)
    |--------------------------------------------------------------------------
    | A church lives on {slug}.{base domain}. The slug is lowercase letters, numbers and single hyphens.
    | These names are kept for the platform itself, so no church can take them (docs/06, Subdomain Strategy).
    */
    'slug_min_length' => 3,
    'slug_max_length' => 40,
    'reserved_slugs' => [
        'www', 'api', 'app', 'admin', 'docs', 'help', 'support', 'status', 'mail', 'email',
        'login', 'register', 'start', 'billing', 'dashboard', 'static', 'assets', 'cdn',
        'staging', 'demo', 'test', 'telescope', 'sanctum', 'storage', 'up',
    ],

    // When a typed slug is taken, these endings are tried to suggest a free one (QA-03 TC-03-02).
    'slug_suggestion_suffixes' => ['gh', 'church', '2'],

    /*
    |--------------------------------------------------------------------------
    | Member number prefix
    |--------------------------------------------------------------------------
    | The capital letters in front of every member number (the GRC in GRC-0042), typed at sign-up (D-030).
    */
    'prefix_min_length' => 2,
    'prefix_max_length' => 6,

];
