<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The church's message log (docs/08, Get Message Log)
|--------------------------------------------------------------------------
|
| Every message the platform sends is written to `message_logs`, but the church's own screen shows only what the church
| sent or would want to read back. The codes for signing in, choosing a password and checking in, and the warning about a
| breached password, are plumbing: their text is left out of the log anyway, and listing them would only show other
| people's phone numbers and emails to staff who have no use for them (agreement section 36, collect and show only what is
| needed). They stay in the table for support and are removed by the same 12-month clean-up.
*/

return [

    // The most parts (credits for each person) one broadcast text may use, so one message cannot cost a church a great deal by
    // accident. Four parts is about 600 characters in the plain alphabet (153 each after the first 160).
    'broadcast_max_parts' => 4,

    'hidden_types' => [
        'otp',
        'onboarding_otp',
        'checkin_otp',
        'sign_in_code',
        'password_reset_code',
        'staff_invitation_code',
        'password_breached',
        'export_ready',
    ],

];
