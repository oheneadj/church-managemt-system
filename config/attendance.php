<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Service check-in code (docs/07, services.checkin_code)
    |--------------------------------------------------------------------------
    | The short code a member adds to a WhatsApp check-in ("CHECKIN GRA7F"). It is unique across all churches, so
    | the code alone tells us which church and service is meant. Letters that look alike (0 and O, 1 and I) are left
    | out because people read these aloud and type them on small phones.
    */
    'checkin_code_length' => 6,
    'checkin_code_alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
    'checkin_code_attempts' => 10,

    /*
    |--------------------------------------------------------------------------
    | Open services that were forgotten
    |--------------------------------------------------------------------------
    | A service still open this many hours after the end of its day is closed by a scheduled command, so a
    | forgotten service does not keep accepting check-ins for ever.
    */
    'stale_open_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | QR code check-in (docs/02 Module 3, QA-05 TC-05-09)
    |--------------------------------------------------------------------------
    | The code on the screen at the venue changes before it expires, so a photo of it stops working. A code is
    | good for `qr_ttl_seconds`, and the display asks for a new one every `qr_refresh_seconds`, which is shorter, so
    | the one a member scans is always still valid for a while.
    */
    'qr_ttl_seconds' => 120,
    'qr_refresh_seconds' => 60,
    'qr_token_length' => 32,

    // The page a member lands on after scanning. It is a page of the React app and is mirrored in resources/js/constants/routes.ts.
    'qr_page_path' => '/checkin',

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    | How far back a member's attendance report looks when no number of weeks is asked for, and the longest it may look.
    */
    'member_report_weeks' => 12,
    // The most members one bulk check-in request may name (a large church marks a whole row at a time).
    'bulk_max' => 500,
    'member_report_max_weeks' => 52,

    /*
    |--------------------------------------------------------------------------
    | Absent members (docs/02, QA-05 TC-05-12)
    |--------------------------------------------------------------------------
    | A member who missed this many services in a row is listed. A church can set its own number in its settings under
    | "absence_threshold". Only these kinds of service count, because a midweek prayer meeting is always smaller and
    | would list people who never miss a Sunday.
    */
    'absence_threshold' => 3,
    'absence_threshold_min' => 2,
    'absence_threshold_max' => 12,
    'absence_service_types' => ['sunday'],

];
