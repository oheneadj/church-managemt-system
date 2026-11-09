<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS credit packages (D-069, story 5.4)
|--------------------------------------------------------------------------
| The limits on a package the platform owner sets, so a typing mistake (an extra zero) is caught before a church is charged
| for it. One credit is one part of a text (D-051). The price is in GHS as exact text (D-017).
*/
return [
    'package_name_max' => 100,
    'package_credits_max' => 1_000_000,
    'package_price_max' => '99999.99',
    'package_sort_order_max' => 1000,

    // Settling a purchase (D-069 step c). A pending purchase is asked about once it is this old, every minute until the provider
    // gives its final word, and one still pending after the second limit is reported so a person can look into it.
    'purchase_check_after_seconds' => 60,
    'purchase_stale_after_minutes' => 60,
];
