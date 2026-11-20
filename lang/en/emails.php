<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Email wording
|--------------------------------------------------------------------------
| The words of the emails the platform sends, kept out of the code so they can be reviewed and later translated
| (DD-009). Plain language, no links to click: a code email must never look like a phishing message.
*/
return [
    'code' => [
        'subject' => 'Your :purpose for :church',
        'intro' => [
            'sign_in' => 'Use this code to finish signing in to :church.',
            'password_reset' => 'Use this code to choose a new password for :church.',
            'staff_invitation' => 'You have been invited to help run :church. Use this code to choose a password and sign in: on the sign-in page, choose “Have an invitation code?”.',
        ],
        'valid_for' => 'The code works once and expires in :minutes minutes.',
        'ignore' => 'If you did not ask for this code, you can ignore this email. Nobody can sign in without it.',
        // What the message log keeps in place of the email: the code itself is never stored.
        'summary' => 'Email with a :purpose (the code is not stored)',
    ],

    // Sent when a CSV export of personal data is ready (D-064). It holds a link that works for a short time, so the log keeps
    // only this summary, never the link.
    'export_ready' => [
        'subject' => 'Your :what export for :church is ready',
        'intro' => 'The :what export you asked for is ready.',
        'download' => 'Download the file',
        'valid_for' => 'The link works for :duration and the file is deleted after :days days. Keep it safe: it lists people\'s contact details.',
        'ignore' => 'If you did not ask for this export, tell your church admin.',
        'summary' => 'Email with the download link for a :what export (the link is not stored)',
    ],

    // Sent after a person chooses a password that is on a public breach list (D-054). It never repeats the password.
    'breached' => [
        'subject' => 'Please choose a new password for :church',
        'intro' => 'The password you just chose appears in a public list of passwords that were exposed in data breaches on other websites. This does not mean our system was breached.',
        'what_to_do' => 'Please choose a different password: go to the sign-in page and choose Forgot password.',
        'reassure' => 'Until you do, your account works as normal. We have not seen anyone misuse it.',
        'summary' => 'Email asking the person to choose a new password',
    ],
];
