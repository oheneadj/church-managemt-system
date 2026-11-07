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
];
