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

];
