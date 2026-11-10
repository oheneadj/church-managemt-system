<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default fund types (docs/04, Fund Types)
    |--------------------------------------------------------------------------
    | Every new church starts with these. The church admin can rename them, add more or switch them off.
    | "Pledge" is not a fund: a gift that pays a pledge is linked to the pledge itself.
    */
    /*
    |--------------------------------------------------------------------------
    | Gift receipts by SMS (D-051, docs/04 Giving Receipts, QA-08)
    |--------------------------------------------------------------------------
    | The words of the text. Plain characters only (a hyphen, no long dash), so the text stays in the cheaper GSM kind and
    | costs one credit. :amount is "GHS 200.00"; :month and :total are the member's giving in the month of the gift.
    | A church can switch cash receipts off in its settings (`receipt_on_cash`); digital gifts always get one.
    */
    'receipt_template' => ":church: :amount received (:fund) on :date.\nYour total giving in :month: :total.\nThank you for your faithfulness.",
    'receipt_date_format' => 'j M Y',
    'receipt_month_format' => 'F',
    'cash_receipts_default' => true,

    // The most a single cash gift may be, in GHS (QA-06 TC-06-05). A digital gift has its own, lower, limit (D-018).
    'cash_max' => '999999.99',

    /*
    |--------------------------------------------------------------------------
    | Digital giving: limits and fees (D-018, D-072, docs/04 Platform Fee)
    |--------------------------------------------------------------------------
    | Amounts are GHS as decimal strings; rates are fractions as decimal strings (0.01 is 1%). They can be changed in the
    | environment without a deploy, and each payout record keeps the fee actually charged, so a change never alters the past.
    | The limits apply to the gift amount, not to the total with the processing fee.
    */
    'min_digital_gift' => env('GIVING_MIN_DIGITAL_GIFT', '1.00'),
    'max_digital_gift' => env('GIVING_MAX_DIGITAL_GIFT', '30000.00'),

    // The platform's fee on each digital gift: the rate, kept between the floor and the cap.
    'platform_fee_rate' => env('GIVING_PLATFORM_FEE_RATE', '0.01'),
    'platform_fee_cap' => env('GIVING_PLATFORM_FEE_CAP', '10.00'),
    'platform_fee_floor' => env('GIVING_PLATFORM_FEE_FLOOR', '0.10'),

    // PayPerlez's published charges (docs/14): collection is paid by the giver on top of the gift; settlement is taken from the payout.
    'collection_fee_rate' => env('GIVING_COLLECTION_FEE_RATE', '0.0199'),
    'settlement_fee_rate' => env('GIVING_SETTLEMENT_FEE_RATE', '0.0159'),

    // A church that settles to a bank account is paid when its settled gifts reach this much (D-072).
    'bank_payout_minimum' => env('GIVING_BANK_PAYOUT_MINIMUM', '100.00'),

    // How many gifts the fund name backfill command handles in one batch.
    'backfill_chunk' => 500,

    // The most a pledge may promise, in GHS, and how many overdue pledges the daily job loads at a time.
    'pledge_max' => '999999.99',
    'pledge_refresh_chunk' => 200,

    // The largest sort position a fund type may be given.
    'sort_order_max' => 1000,

    'default_fund_types' => [
        ['name' => 'Tithe', 'description' => 'One tenth of income, given by committed members.'],
        ['name' => 'Sunday Offering', 'description' => 'The general offering taken at a service.'],
        ['name' => 'Building Fund', 'description' => 'Giving towards the church building.'],
        ['name' => 'Benevolence', 'description' => 'Support for members in need.'],
        ['name' => 'Missions', 'description' => 'Giving towards missions and outreach.'],
        ['name' => 'Special Offering', 'description' => 'Giving for one particular cause or event.'],
        ['name' => 'Other', 'description' => null],
    ],

];
