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
    // The most a single cash gift may be, in GHS (QA-06 TC-06-05). A digital gift has its own, lower, limit (D-018).
    'cash_max' => '999999.99',

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
