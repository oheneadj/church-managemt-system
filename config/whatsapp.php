<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp CHECKIN (docs/14 section 3, QA-05 TC-05-03 to TC-05-07)
    |--------------------------------------------------------------------------
    | A member texts "CHECKIN" or "CHECKIN GRA7F" (the service's check-in code) to the church's WhatsApp number.
    | Which gateway sends the answers is chosen by `services.whatsapp.driver`.
    */
    'command' => 'CHECKIN',

    // The name of the circuit breaker for Meta. `whatsapp:pause` and `whatsapp:resume` use it too.
    'circuit' => 'whatsapp',

    // Meta answers 200 to nothing else, so these are the only header and prefix we look for.
    'signature_header' => 'X-Hub-Signature-256',
    'signature_prefix' => 'sha256=',

    // How the date is written in the answer (25 Oct 2026).
    'date_format' => 'j M Y',

    // Replies to one phone number in one minute. Past this a member who keeps texting gets no more answers (they are still checked in once).
    'replies_per_minute_per_phone' => 5,

    // Replies held back by an open circuit or a pause are put back on the queue, and given up on after the retry window.
    'release_seconds' => 30,
    'retry_minutes' => 30,

    // The wording of every answer. :name, :service and :date are filled in where used.
    'replies' => [
        'checked_in' => '✅ Checked in to :service — :date. Welcome, :name! God bless you. 🙏',
        'already_checked_in' => "You're already checked in! See you inside. 🙏",
        'unknown_number' => "We couldn't find your number in our records. Please contact the church office.",
        'no_open_service' => 'No service is currently open for check-in.',
        'needs_service_code' => 'Your number is in more than one church. Please send CHECKIN followed by the service code, for example CHECKIN GRA7F.',
    ],

];
